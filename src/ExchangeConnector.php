<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class ExchangeConnector
{
    /** @var array|null */
    private $connection;
    /** @var Client */
    private $client;

    /**
     * @param string $exchangeUrl
     *
     * @param Connection|null $connection
     *
     * @throws ConnectorException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __construct(string $exchangeUrl, ?Connection $connection = null)
    {
        $this->client = new Client(['base_uri' => $exchangeUrl]);

        if (null !== $connection) {
            $this->with($connection);
        }
    }

    /**
     * @param Connection $connection
     *
     * @return ExchangeConnector
     *
     * @throws \RuntimeException
     * @throws ConnectorException
     */
    public function with(Connection $connection): self
    {
        $this->connection = ['id' => $this->request('post', 'auth', [], $connection->toArray())];

        return $this;

    }

    /**
     * @param int $depth
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function allowedMarkets(int $depth = 10): array
    {
        return $this->request('get', 'public/allowed_markets', ['depth' => $depth]);
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function allowedSymbols(): array
    {
        return $this->request('get', 'public/allowed_symbols');
    }

    /**
     * @param int $depth
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function markets(int $depth = 10): array
    {
        return $this->request('get', 'public/order_book', ['depth' => $depth]);
    }

    /**
     * @param string $market
     * @param int $depth
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function market(string $market, int $depth = 10): array
    {
        return $this->request('get', sprintf('public/%s/order_book', $market), ['depth' => $depth]);
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function symbols(): array
    {
        return $this->request('get', 'public/symbols');
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws ConnectorException
     */
    public function wallet(): array
    {
        return $this->privateRequest('get', 'account/wallet');
    }

    /**
     * @param int $limit
     *
     * @return array
     *
     * @throws \RuntimeException
     * @throws ConnectorException
     */
    public function orders(int $limit = 10): array
    {
        return $this->privateRequest('get', 'account/history_orders/all', ['limit' => $limit]);
    }

    /**
     * @param string $symbol
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function ordersBySymbol(string $symbol, int $limit = 10): array
    {
        return $this->privateRequest('get', sprintf('account/%s/history_orders', $symbol), ['limit' => $limit]);
    }

    /**
     * @param string $base
     * @param string $symbols
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function ordersList(string $base, string $symbols, int $limit = 10): array
    {
        return $this->privateRequest(
            'get',
            'account/history_orders_list',
            ['base' => $base, 'symbols' => $symbols, 'limit' => $limit]
        );
    }

    /**
     * @param string $side
     * @param string $symbol
     * @param float $amount
     * @param float $price
     * @param float $qty
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function createOrder(string $side, string $symbol, float $price, ?float $qty = null, ?float $amount = null): array
    {
        if ((null !== $qty && null !== $amount) || (null === $qty && null === $amount)) {
            throw new ConnectorException('You should specify only qty or amount');
        }

        return $this->privateRequest('post', sprintf('account/%s/%s', $symbol, $side), [
            'amount' => $amount,
            'price' => $price,
            'qty' => $qty,
        ]);
    }

    /**
     * @param string $symbol
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function cancelOrder(string $symbol): array
    {
        return $this->privateRequest('post', sprintf('account/%s/cancel', $symbol));
    }

    /**
     * @param string $symbol
     *
     * @return array
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    public function openOrders(string $symbol): array
    {
        return $this->privateRequest('get', sprintf('account/%s/open_orders', $symbol));
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws ConnectorException
     */
    public function deposits(): array
    {
        return $this->privateRequest('get', 'account/deposits');
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws ConnectorException
     */
    public function withdrawals(): array
    {
        return $this->privateRequest('get', 'account/withdrowals');
    }

    /**
     * @return bool
     */
    private function authenticated(): bool
    {
        return null !== $this->connection && isset($this->connection['id']);
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     *
     * @return mixed
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    private function privateRequest(string $method, string $endpoint, array $data = [], array $headers = [])
    {
        return $this->request($method, $endpoint, $data, array_merge([], $headers, $this->connection ?? []));
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     *
     * @return mixed
     *
     * @throws ConnectorException
     * @throws \RuntimeException
     */
    private function request(string $method, string $endpoint, array $data = [], array $headers = [])
    {
        if (!$this->authenticated() && 0 !== strpos($endpoint, 'public/') && 'auth' !== $endpoint) {
            throw new ConnectorException('User not specified. Use with()');
        }

        $params = [];

        $params['post' === mb_strtolower($method) ? 'form_params' : 'query'] = $data;
        $params['headers'] = array_merge(['Content-Type' => 'application/json'], $headers);

        try {
            $response = $this->client->request($method, $endpoint, $params)->getBody()->getContents();
        } catch (ClientException | GuzzleException $exception) {
            $response = $exception->getResponse();

            if (!$response) {
                throw new ConnectorException('Request error: ' . $exception->getMessage());
            }

            $json = \json_decode($contents = $response->getBody()->getContents(), true);

            throw new ConnectorException($json['error'] ?? $exception->getMessage());
        }

        return \json_decode($response, true)['result'];

    }
}

