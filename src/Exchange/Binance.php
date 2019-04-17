<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Exchange;

use Binance\API;
use Exception;
use MZNX\ExchangeConnector\BackwardCompatibilityTrait;
use MZNX\ExchangeConnector\Connection;
use MZNX\ExchangeConnector\ConnectorException;
use MZNX\ExchangeConnector\Entity\Candle;
use MZNX\ExchangeConnector\Entity\Deposit;
use MZNX\ExchangeConnector\Entity\OpenOrder;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Entity\OrderBookEntry;
use MZNX\ExchangeConnector\Entity\Symbol as SymbolEntity;
use MZNX\ExchangeConnector\Entity\Withdrawal;
use MZNX\ExchangeConnector\Symbol;
use MZNX\ExchangeConnector\WaitResponse;
use RuntimeException;
use Throwable;

class Binance implements Exchange
{
    use BackwardCompatibilityTrait;

    private $connection;
    /** @var API */
    private $client;

    /**
     * @param Connection|null $connection
     */
    public function __construct(?Connection $connection = null)
    {
        if (null !== $connection) {
            $this->with($connection);
        }
    }

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
     * @return string
     */
    public function auth(Connection $connection): string
    {
        return base64_encode(json_encode($connection->toArray()));
    }

    /**
     * @param Connection $connection
     *
     * @return Exchange
     */
    public function with(Connection $connection): Exchange
    {
        $this->client = new API($connection->getApiKey(), $connection->getSecretKey());

        return $this;
    }

    /**
     * @return bool
     */
    public function authenticated(): bool
    {
        return null !== $this->connection && null !== $this->client;
    }

    /**
     * @deprecated
     *
     * @param Symbol $symbol
     * @param string $interval
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
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
    public function wallet(): array
    {
        try {
            $balances = static::wrapRequest($this->client->balances());
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        return array_merge([], ...array_map(
            static function (array $balance, string $currency) {
                return $balance['available'] > 0.0000001 ? [$currency => $balance['available']] : [];
            },
            $balances,
            array_keys($balances)
        ));
    }

    /**
     * @param int $limit
     *
     * @return WaitResponse|array
     *
     * @throws ConnectorException
     */
    public function orders(int $limit = 10)
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

                if (in_array($asset1.$asset2, $symbols, true)) {
                    $symbol = [$asset2, $asset1];
                } elseif (in_array($asset2 . $asset1, $symbols, true)) {
                    $symbol = [$asset1, $asset2];
                } else {
                    continue;
                }

                $symbolOrders = $this->ordersBySymbol(new Symbol(...$symbol), min($remainingLimit, $limit));
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

        return Order::createFromBinanceResponse($order, $symbol)->toArray();
    }

    /**
     * @param Symbol $symbol
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function ordersBySymbol(Symbol $symbol, int $limit = 10): array
    {
        try {
            $history = static::wrapRequest($this->client->orders($symbol->format(Symbol::BINANCE_FORMAT), 100, $limit));
        } catch (Exception $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        $orders = array_values(array_filter(array_map(
            static function (array $order) use ($symbol) {
                if ($order['status'] === 'NEW' || $order['status'] === 'PARTIALLY_FILLED') {
                    return null;
                }

                return Order::createFromBinanceResponse($order, $symbol)->toArray();
            },
            $history
        )));

        return array_slice($orders, 0, $limit);
    }

    /**
     * @param string $side
     * @param Symbol $symbol
     * @param float $price
     * @param float $qty
     *
     * @return string
     *
     * @throws ConnectorException
     */
    public function createOrder(string $side, Symbol $symbol, float $price, float $qty): string
    {
        try {
            $method = strtolower($side);

            $placedOrder = static::wrapRequest($this->client->$method($symbol->format(Symbol::BINANCE_FORMAT), $qty, $price));

            if (!array_key_exists('orderId', $placedOrder)) {
            }

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
                    static::wrapRequest($this->client->cancel($symbolOrId->format(Symbol::BINANCE_FORMAT), $order['id']));
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
            static function (array $order) use ($symbol) {
                return OpenOrder::createFromBinanceResponse($order, $symbol)->toArray();
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
            static function (array $item) {
                return Deposit::createFromBinanceResponse($item)->toArray();
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

        return [
            'symbol' => $symbol->format(Symbol::STANDARD_FORMAT),
            'bids' => array_map(
                static function (string $price, string $qty) {
                    return OrderBookEntry::createFromBinanceResponse([(float)$price, (float)$qty])->toArray();
                },
                array_keys($orderBook['bids']),
                $orderBook['bids']
            ),
            'asks' => array_map(
                static function (string $price, string $qty) {
                    return OrderBookEntry::createFromBinanceResponse([(float)$price, (float)$qty])->toArray();
                },
                array_keys($orderBook['asks']),
                $orderBook['asks']
            )
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
            static function (array $data) {
                return SymbolEntity::createFromBinanceResponse($data)->toArray();
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