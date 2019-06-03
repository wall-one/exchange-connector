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
use MZNX\ExchangeConnector\External\Okex\Client;
use MZNX\ExchangeConnector\OrderTypes;
use MZNX\ExchangeConnector\Symbol;
use MZNX\ExchangeConnector\WaitResponse;
use Throwable;

class Okex implements Exchange
{
    use BackwardCompatible, Authenticable;

    public const LABEL = 'okex';

    /** @var Client */
    public $client;

    /**
     * @param string $symbol
     *
     * @return Symbol
     */
    public static function splitMarketName(string $symbol): Symbol
    {
        return new Symbol(...explode('-', $symbol));
    }

    /**
     * @param Connection $connection
     *
     * @return Exchange
     */
    public function with(Connection $connection): Exchange
    {
        $this->client = new Client($connection->getApiKey(), $connection->getSecretKey(), $connection->getCustomerId());

        return $this;
    }

    /**
     * @param Symbol $symbol
     * @param string $interval
     * @param int    $limit
     *
     * @return array
     * @throws ConnectorException
     * @deprecated
     *
     */
    public function candles(Symbol $symbol, string $interval, int $limit): array
    {
        throw new ConnectorException('Not implemented yet');
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function wallet(): array
    {
        $response = $this->client->walletInfo();

        return array_merge(...array_map(
            static function (array $balance) {
                return [mb_strtoupper($balance['currency']) => (float)$balance['balance']];
            },
            $response
        ));
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function available(): array
    {
        $response = $this->client->walletInfo();

        return array_merge(...array_map(
            static function (array $balance) {
                return [mb_strtoupper($balance['currency']) => (float)$balance['available']];
            },
            $response
        ));
    }

    /**
     * @param int      $limit
     *
     * @param int|null $orderId
     *
     * @return WaitResponse|array
     *
     * @throws ConnectorException
     */
    public function orders(int $limit = 10, ?int $orderId = null)
    {
        $keys = array_keys($this->wallet());
        $symbols = array_column($this->symbols(), 'symbol');
        $remainingLimit = $limit;

        $orders = [];
        $checkedSymbols = [];
        $stop = false;

        $orderIdFound = false;

        foreach ($keys as $asset1) {
            foreach ($keys as $asset2) {
                if ($asset1 === $asset2) {
                    continue;
                }

                if (in_array($asset1 . '_' . $asset2, $symbols, true)) {
                    $symbol = [$asset2, $asset1];
                } elseif (in_array($asset2 . '_' . $asset1, $symbols, true)) {
                    $symbol = [$asset1, $asset2];
                } else {
                    continue;
                }

                if (in_array(implode('', $symbol), $checkedSymbols, true)) {
                    continue;
                }

                $checkedSymbols[] = implode('', $symbol);

                $symbolOrders = $this->ordersBySymbol(new Symbol(...$symbol), min($remainingLimit, $limit));

                if ($orderId) {
                    $result = [];

                    foreach ($symbolOrders as $order) {
                        if ($order['id'] === $orderId) {
                            $orderIdFound = true;
                        }

                        if ($orderIdFound) {
                            $result[] = $order;
                        }
                    }

                    $symbolOrders = $result;
                }

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
        $order = $this->client->orderInfo($symbol->format(Symbol::OKEX_FORMAT), $id);

        return $this->factory->getFactory(Order::class)
            ->createFromResponse($order)
            ->toArray();
    }

    /**
     * @param Symbol   $symbol
     * @param int      $limit
     *
     * @param int|null $orderId
     *
     * @return array
     * @throws ConnectorException
     */
    public function ordersBySymbol(Symbol $symbol, int $limit = 10, ?int $orderId = null): array
    {
        $allOrders = [];
        $after = null;

        do {
            $orders = $this->client->orders($symbol->format(Symbol::OKEX_FORMAT), $after);
            $allOrders[] = $orders ?? [];
            if ($orders) {
                $after = $orders[count($orders) - 1]['order_id'];
            }
        } while (count($orders) === 100);

        $allOrders = array_merge(...$allOrders);

        if ($orderId) {
            $orderIdFound = false;
            $result = [];

            foreach ($allOrders as $order) {
                if ($order['order_id'] === $orderId) {
                    $orderIdFound = true;
                }

                if ($orderIdFound) {
                    $result[] = $order;
                }
            }

            $allOrders = $result;
        }

        return array_map(
            function (array $order) {
                return $this->factory->getFactory(Order::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            $allOrders
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

        $response = $this->client->placeOrder(
            $type,
            $side,
            $symbol->format(Symbol::OKEX_FORMAT),
            $qty,
            OrderTypes::LIMIT === $type ? $price : null
        );

        return (string)$response['order_id'];
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
        throw new StopLossNotAvailableException('STOP LOSS is not supported on OKEX');
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
        throw new TakeProfitNotAvailableException('TAKE PROFIT is not supported on OKEX');
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
                    $this->client->cancelOrder($order['id'], $symbolOrId->format(Symbol::OKEX_FORMAT));
                }

                return true;
            }

            throw new ConnectorException('Cannot cancel order without symbol');
        } catch (Throwable $t) {
            return false;
        }
    }

    /**
     * @param Symbol $symbol
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function openOrders(Symbol $symbol): array
    {
        $allOrders = [];
        $after = null;

        do {
            $orders = $this->client->openOrders($symbol->format(Symbol::OKEX_FORMAT), $after);

            $allOrders[] = $orders ?? [];
        } while (count($orders) === 100);

        $allOrders = array_merge(...$allOrders);

        return array_map(
            function (array $order) {
                return $this->factory->getFactory(OpenOrder::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            $allOrders
        );
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function deposits(): array
    {
        return array_map(
            function (array $response) {
                return $this->factory->getFactory(Deposit::class)
                    ->createFromResponse($response)
                    ->toArray();
            },
            $this->client->deposits()
        );
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function withdrawals(): array
    {
        return array_map(
            function (array $response) {
                return $this->factory->getFactory(Withdrawal::class)
                    ->createFromResponse($response)
                    ->toArray();
            },
            $this->client->withdrawals()
        );
    }

    /**
     * @param Symbol $symbol
     * @param int    $depth
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function market(Symbol $symbol, int $depth = 10): array
    {
        $orderBook = $this->client->orderBook($depth, $symbol->format(Symbol::STANDARD_FORMAT));

        $adapter = function ($response) {
            return $this->factory->getFactory(OrderBookEntry::class)
                ->createFromResponse($response)
                ->toArray();
        };

        return [
            'symbol' => $symbol->format(Symbol::STANDARD_FORMAT),
            'bids' => array_map($adapter, $orderBook['bids']),
            'asks' => array_map($adapter, $orderBook['asks'])
        ];
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function symbols(): array
    {
        $symbols = $this->client->instruments();

        return array_map(
            function (array $instrument) {
                return $this->factory->getFactory(SymbolEntity::class)
                    ->createFromResponse($instrument)
                    ->toArray();
            },
            $symbols
        );
    }
}
