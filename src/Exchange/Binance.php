<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Exchange;

use Binance\API;
use Exception;
use MZNX\ExchangeConnector\Authenticable;
use MZNX\ExchangeConnector\BackwardCompatible;
use MZNX\ExchangeConnector\Connection;
use MZNX\ExchangeConnector\ConnectorException;
use MZNX\ExchangeConnector\Entity\Candle;
use MZNX\ExchangeConnector\Entity\Deposit;
use MZNX\ExchangeConnector\Entity\OpenOrder;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Entity\OrderBookEntry;
use MZNX\ExchangeConnector\Entity\Symbol as SymbolEntity;
use MZNX\ExchangeConnector\Entity\Withdrawal;
use MZNX\ExchangeConnector\OrderTypes;
use MZNX\ExchangeConnector\Symbol;
use MZNX\ExchangeConnector\WaitResponse;
use RuntimeException;
use Throwable;

class Binance implements Exchange
{
    use BackwardCompatible, Authenticable;

    public const LABEL = 'binance';

    private $connection;
    /** @var API */
    private $client;

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
        /*
         * I'm to lazy to make proxy class so I'll use anonymous class :)
         * Maybe I'll fix it later
         */
        $this->client = new class($connection->getApiKey(), $connection->getSecretKey())
        {
            private $client;

            public function __construct(string $key, string $secret)
            {
                $this->client = new class($key, $secret) extends API
                {
                    protected $caOverride = true;
                };
            }

            public function __call($name, $arguments)
            {
                if (!method_exists($this->client, $name)) {
                    throw new RuntimeException(sprintf('Undefined method %s::%s', get_class($this->client), $name));
                }

                ob_start();
                $result = call_user_func_array([$this->client, $name], $arguments);
                ob_end_clean();

                return $result;
            }
        };

        return $this;
    }

    /**
     * @param Symbol $symbol
     * @param string $interval
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
     * @deprecated
     *
     */
    public function candles(Symbol $symbol, string $interval, int $limit): array
    {
        try {
            return array_values(
                array_map(
                    static function (array $candle) {
                        return Candle::createFromBinanceResponse($candle)->toArray();
                    },
                    $this->client->candlesticks($symbol->format(Symbol::BINANCE_FORMAT), $interval)
                )
            );
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function available(): array
    {
        try {
            $balances = static::wrapRequest($this->client->balances());
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        return array_merge([], ...array_map(
            static function (array $balance, string $currency) {
                return $balance['available'] > 0.0000001 ? [mb_strtoupper($currency) => $balance['available']] : [];
            },
            $balances,
            array_keys($balances)
        ));
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function wallet(): array
    {
        try {
            $balances = static::wrapRequest($this->client->balances());
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        return array_merge([], ...array_map(
            static function (array $balance, string $currency) {
                $total = $balance['available'] + $balance['onOrder'];

                return $total > 0.0000001 ? [mb_strtoupper($currency) => $total] : [];
            },
            $balances,
            array_keys($balances)
        ));
    }

    /**
     * @param int $limit
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
        $symbols = array_column($this->symbols(), 'id');
        $remainingLimit = $limit;

        $orders = [];
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

        return array_merge(...$orders);
    }

    /**
     * @param Symbol $symbol
     * @param $id
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function orderInfo(Symbol $symbol, $id): array
    {
        try {
            $order = static::wrapRequest($this->client->orderStatus($symbol->format(Symbol::BINANCE_FORMAT), $id));
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        $order['symbol'] = $symbol;

        return $this->factory
            ->getFactory(Order::class)
            ->createFromResponse($order)
            ->toArray();
    }

    /**
     * @param Symbol $symbol
     * @param int $limit
     *
     * @param int|null $orderId
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function ordersBySymbol(Symbol $symbol, int $limit = 10, ?int $orderId = null): array
    {
        if (!$orderId) {
            $orderId = 1;
        }

        try {
            $history = static::wrapRequest($this->client->orders($symbol->format(Symbol::BINANCE_FORMAT), $limit,
                $orderId));
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        if (!is_array($history)) {
            return [];
        }

        $orders = array_values(array_filter(array_map(
            function (array $order) use ($symbol) {
                if ($order['status'] === 'NEW' || $order['status'] === 'PARTIALLY_FILLED') {
                    return null;
                }

                $order['symbol'] = $symbol;

                return $this->factory
                    ->getFactory(Order::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            $history
        )));

        return array_slice($orders, 0, $limit);
    }

    /**
     * @param string $type
     * @param string $side
     * @param Symbol $symbol
     * @param float $price
     * @param float $qty
     *
     * @return string
     *
     * @throws ConnectorException
     */
    public function createOrder(string $type, string $side, Symbol $symbol, float $price, float $qty): string
    {
        try {
            if ($type === OrderTypes::LIMIT) {
                $method = strtolower($side);
            } elseif ($type === OrderTypes::MARKET) {
                $method = 'market' . ucfirst(strtolower($side));
            } else {
                throw new ConnectorException(sprintf(
                    'Unknown order type %s. See %s to get allowed order types',
                    $type,
                    OrderTypes::class
                ));
            }

            $placedOrder = static::wrapRequest(
                $this->client->$method($symbol->format(Symbol::BINANCE_FORMAT), $qty, $price)
            );

            return (string)$placedOrder['orderId'];
        } catch (Throwable $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }
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
                foreach ($this->openOrders($symbolOrId) as $order) {
                    static::wrapRequest($this->client->cancel($symbolOrId->format(Symbol::BINANCE_FORMAT),
                        $order['id']));
                }

                return true;
            }

            // CANNOT CANCEL ORDER BY ID WITHOUT SYMBOL
            return false;
        } catch (Exception $e) {
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
        try {
            $orders = static::wrapRequest($this->client->openOrders($symbol->format(Symbol::BINANCE_FORMAT)));
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        return array_map(
            function (array $order) use ($symbol) {
                $order['symbol'] = $symbol;

                return $this->factory->getFactory(OpenOrder::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            $orders
        );
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function deposits(): array
    {
        try {
            $history = static::wrapRequest($this->client->depositHistory());
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        return array_map(
            function (array $item) {
                return $this->factory->getFactory(Deposit::class)
                    ->createFromResponse($item)
                    ->toArray();
            },
            $history['depositList']
        );
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function withdrawals(): array
    {
        try {
            $history = static::wrapRequest($this->client->withdrawHistory());
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        return array_map(
            static function (array $item) {
                return Withdrawal::createFromBinanceResponse($item)->toArray();
            },
            $history['withdrawList']
        );
    }

    /**
     * @param Symbol $symbol
     * @param int $depth
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function market(Symbol $symbol, int $depth = 10): array
    {
        try {
            $orderBook = static::wrapRequest($this->client->depth($symbol->format(Symbol::BINANCE_FORMAT), $depth));
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        $adapter = function (string $price, string $qty) {
            return $this->factory->getFactory(OrderBookEntry::class)
                ->createFromResponse([(float)$price, (float)$qty])
                ->toArray();
        };

        return [
            'symbol' => $symbol->format(Symbol::STANDARD_FORMAT),
            'bids' => array_map($adapter, array_keys($orderBook['bids']), $orderBook['bids']),
            'asks' => array_map($adapter, array_keys($orderBook['asks']), $orderBook['asks'])
        ];
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function symbols(): array
    {
        try {
            $exchangeInfo = static::wrapRequest($this->client->exchangeInfo());
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        return array_map(
            function (array $data) {
                return $this->factory
                    ->getFactory(SymbolEntity::class)
                    ->createFromResponse($data)
                    ->toArray();
            },
            $exchangeInfo['symbols']
        );
    }

    /**
     * @param $data
     *
     * @return mixed
     *
     * @throws RuntimeException
     */
    private static function wrapRequest($data)
    {
        if (isset($data['msg'])) {
            throw new RuntimeException($data['msg']);
        }

        return $data;
    }
}
