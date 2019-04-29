<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Exchange;

use codenixsv\Bittrex\BittrexManager;
use codenixsv\Bittrex\Clients\BittrexClient;
use Exception;
use MZNX\ExchangeConnector\Authenticable;
use MZNX\ExchangeConnector\BackwardCompatible;
use MZNX\ExchangeConnector\ConnectorException;
use MZNX\ExchangeConnector\Entity\Deposit;
use MZNX\ExchangeConnector\Entity\OpenOrder;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Entity\OrderBookEntry;
use MZNX\ExchangeConnector\Entity\Symbol;
use MZNX\ExchangeConnector\Entity\Symbol as SymbolEntity;
use MZNX\ExchangeConnector\Entity\Withdrawal;
use MZNX\ExchangeConnector\Connection;
use MZNX\ExchangeConnector\Symbol as ExchangeSymbol;
use Throwable;

class Bittrex implements Exchange
{
    use BackwardCompatible, Authenticable;

    public const LABEL = 'bittrex';

    private const DELIMITER = '-';
    /**
     * @var array
     */
    private $connection;
    /** @var BittrexClient|null */
    private $client;

    /**
     * @param string $symbol
     *
     * @return ExchangeSymbol
     */
    public static function splitMarketName(string $symbol): ExchangeSymbol
    {
        [$base, $quote] = explode(static::DELIMITER, $symbol);

        return new ExchangeSymbol($base, $quote);
    }

    /**
     * @param Connection $connection
     *
     * @return Exchange
     */
    public function with(Connection $connection): Exchange
    {
        $this->client = (new BittrexManager($connection->getApiKey(), $connection->getSecretKey()))->createClient();

        return $this;
    }

    /**
     * @deprecated don't use this method
     *
     * @param ExchangeSymbol $symbol
     * @param string $interval
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function candles(ExchangeSymbol $symbol, string $interval, int $limit): array
    {
        throw new ConnectorException('Not implemented yet');
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function available(): array
    {
        return array_reduce(
            static::wrapRequest($this->client->getBalances()),
            static function (array $acc, array $item) {
                if ($item['Available'] > 0.0000001) {
                    $acc[mb_strtolower($item['Currency'])] = (float)$item['Available'];
                }

                return $acc;
            },
            []
        );
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function wallet(): array
    {
        return array_reduce(
            static::wrapRequest($this->client->getBalances()),
            static function (array $acc, array $item) {
                if ($item['Balance'] > 0.0000001) {
                    $acc[mb_strtolower($item['Currency'])] = (float)$item['Balance'];
                }

                return $acc;
            },
            []
        );
    }

    /**
     * @param int $limit
     *
     * @param int|null $orderId
     * @return array
     *
     * @throws ConnectorException
     */
    public function orders(?int $limit = 10, ?int $orderId = null): array
    {
        $orders = static::wrapRequest($this->client->getOrderHistory());

        $orders =  array_map(
            static function (array $order) {
                return $this->factory
                    ->getFactory(Order::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            $limit ? array_slice($orders, 0, $limit) : $orders
        );

        if ( $orderId ) {
            $orders = array_filter( $orders,
                static function( array $order ) use ( $orderId ) {
                    return $order['id'] > $orderId;
                });
        }

        return $orders;
    }

    /**
     * @param ExchangeSymbol $symbol
     * @param $id
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws Exception
     */
    public function orderInfo(ExchangeSymbol $symbol, $id): array
    {
        $order = static::wrapRequest($this->client->getOrder($id));

        return $this->factory
            ->getFactory(Order::class)
            ->createFromResponse($order)
            ->toArray();
    }

    /**
     * @param ExchangeSymbol $symbol
     * @param int|null $limit
     *
     * @param int|null $orderId
     * @return array
     *
     * @throws ConnectorException
     */
    public function ordersBySymbol(ExchangeSymbol $symbol, ?int $limit = 10, ?int $orderId = null): array
    {
        $orders = array_filter(
            $this->orders(null, $orderId ),
            static function (array $order) use ($symbol) {
                return $order['symbol'] === $symbol->format(ExchangeSymbol::STANDARD_FORMAT);
            }
        );

        return $limit ? array_slice($orders, 0, $limit) : $orders;
    }

    /**
     * @param string $side
     * @param ExchangeSymbol $symbol
     * @param float $price
     * @param float $qty
     *
     * @return string
     *
     * @throws ConnectorException
     */
    public function createOrder(string $side, ExchangeSymbol $symbol, float $price, float $qty): string
    {
        if (!in_array(mb_strtolower($side), ['buy', 'sell'], true)) {
            throw new ConnectorException('Unknown side ' . $side);
        }

        $method = mb_strtolower($side) . 'Limit';

        return static::wrapRequest(
            $this->client->$method($symbol->format(ExchangeSymbol::BITTREX_FORMAT), $qty, $price)
        )['uuid'];
    }

    /**
     * @param int|string $symbolOrId
     *
     * @return bool
     */
    public function cancelOrder($symbolOrId): bool
    {
        try {
            if ($symbolOrId instanceof ExchangeSymbol) {
                $orders = $this->openOrders($symbolOrId);

                foreach ($orders as $order) {
                    $this->cancelOrder($order['id']);
                }

                return true;
            }

            static::wrapRequest($this->client->cancel($symbolOrId));

            return true;
        } catch (Throwable $t) {
            return false;
        }
    }

    /**
     * @param ExchangeSymbol $symbol
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function openOrders(ExchangeSymbol $symbol): array
    {
        return array_map(
            static function (array $order) {
                return $this->factory->getFactory(OpenOrder::class)
                    ->createFromResponse($order)
                    ->toArray();
            },
            static::wrapRequest($this->client->getOpenOrders($symbol->format(ExchangeSymbol::BITTREX_FORMAT)))
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
            static function (array $deposit) {
                return $this->factory->getFactory(Deposit::class)
                    ->createFromResponse($deposit)
                    ->toArray();
            },
            static::wrapRequest($this->client->getDepositHistory())
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
            static function (array $withdrawal) {
                return Withdrawal::createFromBittrexResponse($withdrawal)->toArray();
            },
            static::wrapRequest($this->client->getWithdrawalHistory())
        );
    }

    /**
     * @param ExchangeSymbol $symbol
     * @param int $depth
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function market(ExchangeSymbol $symbol, int $depth = 10): array
    {
        $orderBook = static::wrapRequest($this->client->getOrderBook($symbol->format(ExchangeSymbol::BITTREX_FORMAT)));
        $adapter = function (array $entry) {
            return $this->factory->getFactory(OrderBookEntry::class)
                ->createFromResponse($entry)
                ->toArray();
        };

        return [
            'symbol' => $symbol->format(ExchangeSymbol::STANDARD_FORMAT),
            'bids' => array_slice(array_map($adapter, $orderBook['buy']), 0, $depth),
            'asks' => array_slice(array_map($adapter, $orderBook['sell']), 0, $depth)
        ];
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function symbols(): array
    {
        $symbols = static::wrapRequest($this->client->getMarkets());

        return array_map(
            static function (array $symbol) {
                return $this->factory
                    ->getFactory(SymbolEntity::class)
                    ->createFromResponse($symbol)
                    ->toArray();
            },
            $symbols
        );
    }

    /**
     * @param $data
     *
     * @return array
     *
     * @throws ConnectorException
     */
    private static function wrapRequest($data): array
    {
        $json = json_decode($data, true);

        if (!$json['success']) {
            throw new ConnectorException($json['message'] ?? 'NO_RESPONSE');
        }

        return $json['result'];

    }
}
