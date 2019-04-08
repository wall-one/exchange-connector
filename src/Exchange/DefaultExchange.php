<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Exchange;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use function json_decode;
use MZNX\ExchangeConnector\Connection;
use MZNX\ExchangeConnector\ConnectorException;
use MZNX\ExchangeConnector\WaitResponse;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class DefaultExchange implements Exchange
{
    private const DELIMITER = '_';

    /**
     * @var array
     */
    private $connection;
    /**
     * @var Client
     */
    private $client;

    /**
     * Returns array [$base, $quote]
     *
     * @param string $symbol
     *
     * @return array
     */
    public static function splitMarketName(string $symbol): array
    {
        [$quote, $base] = array_map('mb_strtoupper', explode(static::DELIMITER, $symbol));

        return [$base, $quote];
    }

    /**
     * @param string $exchangeUrl
     * @param Connection|null $connection
     *
     * @throws ConnectorException
     */
    public function __construct(string $exchangeUrl, ?Connection $connection = null)
    {
        try {
            $this->client = new Client(['base_uri' => $exchangeUrl]);
        } catch (InvalidArgumentException $e) {
            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }

        if (null !== $connection) {
            $this->with($connection);
        }
    }

    /**
     * @param Connection $connection
     *
     * @return string
     *
     * @throws ConnectorException
     */
    public function auth(Connection $connection): string
    {
        return $this->request('post', 'auth', [], $connection->toArray());
    }

    /**
     * @param Connection $connection
     *
     * @return DefaultExchange
     *
     * @throws ConnectorException
     */
    public function with(Connection $connection): Exchange
    {
        $this->connection = ['id' => $this->auth($connection)];

        return $this;
    }

    /**
     * @return bool
     */
    public function authenticated(): bool
    {
        return null !== $this->connection && isset($this->connection['id']);
    }

    /**
     * @deprecated Will be removed in 2.0. Use nomics instead
     *
     * @param string $symbol
     * @param string $interval
     * @param int $limit
     * @return array
     * @throws ConnectorException
     */
    public function candles(string $symbol, string $interval, int $limit): array
    {
        $query = [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit
        ];

        return $this->request('get', 'public/klines', $query);
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function wallet(): array
    {
        return $this->request('get', 'account/wallet');
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
        return $this->request('get', 'account/history_orders/all', ['limit' => $limit]);
    }

    /**
     * @param string $symbol
     * @param string|int $id
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function orderInfo(string $symbol, $id): array
    {
        return $this->request('get', sprintf('account/%s/orderinfo', $symbol), ['orderId' => $id]);
    }

    /**
     * @param string $symbol
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function ordersBySymbol(string $symbol, int $limit = 10): array
    {
        return $this->request('get', sprintf('account/%s/history_orders', $symbol), ['limit' => $limit]);
    }

    /**
     * @param string $side
     * @param string $symbol
     * @param float $price
     * @param float $qty
     *
     * @return string
     *
     * @throws ConnectorException
     */
    public function createOrder(
        string $side,
        string $symbol,
        float $price,
        float $qty
    ): string {
        return $this->request('post', sprintf('account/%s/%s', $symbol, $side), [
            'price' => $price,
            'qty' => $qty,
        ]);
    }

    /**
     * @param string|int $symbolOrId
     *
     * @return bool
     *
     * @throws ConnectorException
     */
    public function cancelOrder($symbolOrId): bool
    {
        $result = $this->request('post', sprintf('account/%s/cancel', $symbolOrId));

        return (bool)$result;
    }

    /**
     * @param string $symbol
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function openOrders(string $symbol): array
    {
        return $this->request('get', sprintf('account/%s/open_orders', $symbol));
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function deposits(): array
    {
        return $this->request('get', 'account/deposits');
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function withdrawals(): array
    {
        return $this->request('get', 'account/withdrowals');
    }

    /**
     * @param string $market
     * @param int $depth
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function market(string $market, int $depth = 10): array
    {
        return $this->request('get', sprintf('public/%s/order_book', $market), ['depth' => $depth]);
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function symbols(): array
    {
        return $this->request('get', 'public/symbols');
    }

    /**
     * !!!!REFACTOR ME PLEASE!!!!
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     *
     * @return mixed
     *
     * @throws ConnectorException
     */
    private function request(string $method, string $endpoint, array $data = [], array $headers = [])
    {
        if ('auth' !== $endpoint && !$this->authenticated()) {
            throw new ConnectorException('User not specified. Use with()');
        }

        try {
            $headers = array_merge([], $headers, $this->connection ?? []);

            $params = [];

            $params['post' === mb_strtolower($method) ? 'form_params' : 'query'] = $data;
            $params['headers'] = array_merge([
                'Content-Type' => 'post' === mb_strtolower($method)
                    ? 'application/x-www-form-urlencoded'
                    : 'application/json'
            ], $headers);

            try {
                $response = $this->client->request($method, $endpoint, $params)->getBody()->getContents();
            } catch (GuzzleException $exception) {
                $response = method_exists($exception, 'getResponse') ? $exception->getResponse() : null;

                if (!$response) {
                    throw new ConnectorException('Request error: ' . $exception->getMessage());
                }

                /** @var ResponseInterface $response */
                $json = json_decode($contents = $response->getBody()->getContents(), true);
                $errorMessage = $json['error'];

                if ($json['message'] ?? '') {
                    $errorMessage .= ':' . $json['message'];
                }

                throw new ConnectorException($errorMessage ?? $exception->getMessage());
            }

            $json = json_decode($response, true);
            $result = $json['result'] ?? [];

            if (!$result && ($json['message'] ?? '') === 'WAIT') {
                return new WaitResponse();
            }

            return $result;
        } catch (Exception | Throwable $e) {
            throw new ConnectorException($e->getMessage());
        }
    }
}
