<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Exchange;

use MZNX\ExchangeConnector\Authenticable;
use MZNX\ExchangeConnector\BackwardCompatible;
use MZNX\ExchangeConnector\Connection;
use MZNX\ExchangeConnector\ConnectorException;
use MZNX\ExchangeConnector\Entity\Deposit;
use MZNX\ExchangeConnector\Entity\OpenOrder;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Entity\OrderBookEntry;
use MZNX\ExchangeConnector\Entity\Symbol as SymbolEntity;
use MZNX\ExchangeConnector\Entity\Withdrawal;
use MZNX\ExchangeConnector\Exception\StopLossNotAvailableException;
use MZNX\ExchangeConnector\Exception\TakeProfitNotAvailableException;
use MZNX\ExchangeConnector\OrderTypes;
use MZNX\ExchangeConnector\Symbol;
use Req;
use RuntimeException;
use Throwable;

class Huobi implements Exchange
{
    use BackwardCompatible, Authenticable;

    public const LABEL = 'huobi';
    public const LABEL_RU = 'huobi_ru';
    public const LABEL_US = 'huobi_us';
    public const LABEL_CH = 'huobi_ch';

    /** @var Req */
    public $client;

    /**
     * @param string $symbol
     *
     * @return Symbol
     *
     * @throws RuntimeException
     */
    public static function splitMarketName(string $symbol): Symbol
    {
        throw new RuntimeException('Not implemented yet');
    }

    /**
     * @param Connection $connection
     *
     * @return Exchange
     */
    public function with(Connection $connection): Exchange
    {
        $this->client = new class($connection->getCustomerId(), $connection->getApiKey(), $connection->getSecretKey())
        {
            private $client;

            public function __construct(string $region, string $apiKey, string $secretKey)
            {
                $this->client = new Req($region, $apiKey, $secretKey);
            }

            public function __call($name, $arguments)
            {
                if (!method_exists($this->client, $name)) {
                    throw new RuntimeException("Method $name does not exists in " . get_class($this->client));
                }

                $result = call_user_func_array([$this->client, $name], $arguments);

                if ($result['status'] !== 'ok') {
                    throw new ConnectorException($result['err-msg'] ?? 'Cannot connect to excange');
                }

                return $result;
            }
        };

        return $this;
    }

    /**
     * @param Symbol $symbol
     * @param string $interval
     * @param int    $limit
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @deprecated
     *
     */
    public function candles(Symbol $symbol, string $interval, int $limit): array
    {
        throw new RuntimeException('Not implemented yet');
    }

    /**
     * @return array
     */
    public function wallet(): array
    {
        return array_reduce(
            $this->client->get_account_accounts()['data'],
            function (array $acc, array $item) {
                if (mb_strtolower($item['type']) !== 'spot' || mb_strtolower($item['state']) !== 'working') {
                    return $acc;
                }

                $acc = array_merge(
                    $acc,
                    array_reduce(
                        $this->client->get_balance($item['id'])['data']['list'],
                        static function (array $acc, array $item) {
                            if ($item['balance'] <= 0.0000001) {
                                return $acc;
                            }

                            $currency = mb_strtolower($item['currency']);
                            $acc[mb_strtoupper($currency)] = ($acc[$currency] ?? 0.) + $item['balance'];

                            return $acc;
                        },
                        []
                    )
                );

                return $acc;
            },
            []
        );
    }

    /**
     * @return array
     */
    public function available(): array
    {
        return array_reduce(
            $this->client->get_account_accounts()['data'],
            function (array $acc, array $item) {
                if (mb_strtolower($item['type']) !== 'spot' || mb_strtolower($item['state']) !== 'working') {
                    return $acc;
                }

                $acc = array_merge(
                    $acc,
                    array_reduce(
                        $this->client->get_balance($item['id'])['data']['list'],
                        static function (array $acc, array $item) {
                            if ($item['type'] !== 'trade' || $item['balance'] <= 0.0000001) {
                                return $acc;
                            }

                            $acc[mb_strtoupper($item['currency'])] = $item['balance'];

                            return $acc;
                        },
                        []
                    )
                );

                return $acc;
            },
            []
        );
    }

    /**
     * @param int      $limit
     * @param int|null $orderId
     *
     * @return WaitResponse|array
     */
    public function orders(int $limit = 10, int $orderId = null)
    {
        $keys = array_keys($this->wallet());
        $symbols = array_column($this->symbols(), 'id');
        $remainingLimit = $limit;

        $orders = [];
        $checkedSymbols = [];
        $stop = false;

        foreach ($keys as $asset1) {
            foreach ($keys as $asset2) {
                if ($asset1 === $asset2) {
                    continue;
                }

                if (in_array($asset1 . $asset2, $symbols, true)) {
                    $symbol = [$asset2, $asset1];
                } elseif (in_array($asset2 . $asset1, $symbols, true)) {
                    $symbol = [$asset1, $asset2];
                } else {
                    continue;
                }

                if (in_array(implode('', $symbol), $checkedSymbols, true)) {
                    continue;
                }

                $checkedSymbols[] = implode('', $symbol);

                $symbolOrders = $this->ordersBySymbol(new Symbol(...$symbol), min($remainingLimit, $limit), $orderId);
                $orders[] = array_slice($symbolOrders, 0, $remainingLimit);

                $remainingLimit -= min($remainingLimit, count($symbolOrders));

                if ($remainingLimit <= 0) {
                    $stop = true;
                    break;
                }
            }

            if ($stop) {
                break;
            }
        }

        return array_merge([], ...$orders);
    }

    /**
     * @param Symbol $symbol
     * @param        $id
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function orderInfo(Symbol $symbol, $id): array
    {
        $order = $this->client->get_order($id)['data'];
        $order['symbol'] = $symbol;

        return $this->factory->getFactory(Order::class)
            ->createFromResponse($order)
            ->toArray();
    }

    /**
     * @param Symbol   $symbol
     * @param int      $limit
     * @param int|null $orderId
     *
     * @return array
     */
    public function ordersBySymbol(Symbol $symbol, int $limit = 10, int $orderId = null): array
    {

        $orders = $this->client->get_order_orders(
            $symbol->format(Symbol::HUOBI_FORMAT),
            'buy-market,sell-market,buy-ioc,sell-ioc,buy-limit,sell-limit',
            '2017-01-01',
            date('Y-m-d'),
            'partial-canceled,filled,canceled',
            $orderId,
            '',
            $limit
        );

        return array_map(
            function (array $order) use ($symbol) {
                $order['symbol'] = $symbol;

                return $this->factory
                    ->getFactory(Order::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            $orders['data']
        );
    }

    /**
     * @param string $type
     * @param string $side
     * @param Symbol $symbol
     * @param float  $price
     * @param float  $qty
     *
     * @return string
     *
     * @throws ConnectorException
     */
    public function createOrder(string $type, string $side, Symbol $symbol, float $price, float $qty): string
    {
        if (!in_array($type, [OrderTypes::LIMIT, OrderTypes::MARKET], true)) {
            throw new ConnectorException(sprintf(
                'Unknown order type %s. See %s to get allowed order types',
                $type,
                OrderTypes::class
            ));
        }

        foreach ($this->client->get_account_accounts()['data'] as $item) {
            if (mb_strtolower($item['type']) === 'spot' && mb_strtolower($item['state']) === 'working') {
                return $this->client->place_order(
                    $item['id'],
                    $qty,
                    $price,
                    $symbol->format(Symbol::HUOBI_FORMAT),
                    mb_strtolower($side) . '-' . mb_strtolower($type)
                )['data'];
            }
        }
    }

    /**
     * @param string $side
     * @param Symbol $symbol
     * @param float  $price
     * @param float  $qty
     * @param float  $stopPrice
     *
     * @return string
     *
     * @throws StopLossNotAvailableException
     */
    public function stopLoss(string $side, Symbol $symbol, float $price, float $qty, float $stopPrice): string
    {
        throw new StopLossNotAvailableException('STOP LOSS is not supported on Huobi');
    }

    /**
     * @param string $side
     * @param Symbol $symbol
     * @param float  $price
     * @param float  $qty
     * @param float  $stopPrice
     *
     * @return string
     *
     * @throws TakeProfitNotAvailableException
     */
    public function takeProfit(string $side, Symbol $symbol, float $price, float $qty, float $stopPrice): string
    {
        throw new TakeProfitNotAvailableException('TAKE PROFIT is not supported on Huobi');
    }

    /**
     * @param Symbol|int $symbolOrId
     *
     * @return bool
     */
    public function cancelOrder($symbolOrId): bool
    {
        try {
            if ($symbolOrId instanceof Symbol) {
                $orders = $this->openOrders($symbolOrId);

                foreach ($orders as $order) {
                    $this->cancelOrder($order['id']);
                }

                return true;
            }

            $response = $this->client->cancel_order($symbolOrId);

            return $response['status'] === 'ok';
        } catch (Throwable $t) {
            return false;
        }
    }

    /**
     * @param Symbol $symbol
     *
     * @return array
     */
    public function openOrders(Symbol $symbol): array
    {
        $orders = $this->client->get_order_orders(
            $symbol->format(Symbol::HUOBI_FORMAT),
            'buy-market,sell-market,buy-ioc,sell-ioc,buy-limit,sell-limit',
            '',
            '',
            'pre-submitted,submitted,partial-filled'
        );

        return array_map(
            function (array $order) use ($symbol) {
                $order['symbol'] = $symbol;

                return $this->factory
                    ->getFactory(OpenOrder::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            $orders['data']
        );
    }

    /**
     * @return array
     */
    public function deposits(): array
    {
        $deposits = [];

        $bases = array_map('strtolower', array_keys($this->wallet()));

        foreach ($bases as $base) {
            $from = 0;

            $continue = true;

            while ($continue) {
                $temp = $this->client->get_deposit_withdrawals($base, 'deposit', $from)['data'];

                if (!$temp) {
                    break;
                }

                if (count($temp) < 100) {
                    $continue = false;
                } else {
                    $from = $deposits[count($deposits) - 1]['id'];
                }

                $deposits = array_merge($deposits, $temp);
            }
        }

        return array_map(
            function (array $deposit) {
                return $this->factory->getFactory(Deposit::class)
                    ->createFromResponse($deposit)
                    ->toArray();
            },
            $deposits
        );
    }

    /**
     * @return array
     */
    public function withdrawals(): array
    {
        $withdrawals = [];

        $bases = array_map('strtolower', array_keys($this->wallet()));

        foreach ($bases as $base) {
            $from = 0;

            $continue = true;

            while ($continue) {
                $temp = $this->client->get_deposit_withdrawals($base, 'withdraw', $from)['data'];

                if (!$temp) {
                    break;
                }

                if (count($temp) < 100) {
                    $continue = false;
                } else {
                    $from = $withdrawals[count($withdrawals) - 1]['id'];
                }

                $withdrawals = array_merge($withdrawals, $temp);
            }
        }

        return array_map(
            function (array $withdrawal) {
                return $this->factory->getFactory(Withdrawal::class)
                    ->createFromResponse($withdrawal)
                    ->toArray();
            },
            $withdrawals
        );
    }

    /**
     * @param Symbol $symbol
     * @param int    $depth
     *
     * @return array
     */
    public function market(Symbol $symbol, int $depth = 10): array
    {
        $orderBook = $this->client->get_market_depth($symbol->format(Symbol::HUOBI_FORMAT), 'step0')['tick'];
        $adapter = function (array $entry) {
            return $this->factory->getFactory(OrderBookEntry::class)
                ->createFromResponse($entry)
                ->toArray();
        };

        return [
            'symbol' => $symbol->format(Symbol::STANDARD_FORMAT),
            'bids' => array_map($adapter, array_slice($orderBook['bids'], 0, $depth)),
            'asks' => array_map($adapter, array_slice($orderBook['asks'], 0, $depth))
        ];
    }

    /**
     * @return array
     */
    public function symbols(): array
    {
        return array_map(
            function (array $symbol) {
                return $this->factory
                    ->getFactory(SymbolEntity::class)
                    ->createFromResponse($symbol)
                    ->toArray();
            },
            $this->client->get_common_symbols()['data']
        );
    }
}
