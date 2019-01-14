<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ExchangeConnector
{
    /** @var array|null */
    private $connection;
    /** @var Client */
    private $client;
    /** @var CacheInterface|null */
    private $cache;

    private $cacheKey;
    private $cacheTime;

    /**
     * @param string $base
     * @param string $quote
     * 
     * @return string
     */
    public static function buildMarketName(string $base, string $quote): string
    {
        return mb_strtoupper(sprintf('%s_%s', $quote, $base));
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
        } catch (\InvalidArgumentException $e) {
            throw new ConnectorException($e->getMessage());
        }

        if (null !== $connection) {
            $this->with($connection);
        }
    }

    /**
     * @param CacheInterface $cache
     *
     * @return ExchangeConnector
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * @param int $ttl
     * @param null|string $key
     *
     * @return ExchangeConnector
     * @throws ConnectorException
     */
    public function cache(int $ttl, ?string $key = null): self
    {
        if (!$this->cache instanceof CacheInterface) {
            throw new ConnectorException('Cache is not defined. Use setCache() first!');
        }

        $this->cacheTime = $ttl;
        $this->cacheKey = $key;

        return $this;
    }

    /**
     * @param null|string $key
     *
     * @return ExchangeConnector
     *
     * @throws ConnectorException
     */
    public function cacheForever(?string $key = null): self
    {
        return $this->cache(-1, $key);
    }

    /**
     * @param Connection $connection
     *
     * @return ExchangeConnector
     *
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
     */
    public function allowedMarkets(int $depth = 10): array
    {
        return $this->request('get', 'public/allowed_markets', ['depth' => $depth]);
    }

    /**
     * @return array
     *
     * @throws ConnectorException
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
     * @return array
     *
     * @throws ConnectorException
     */
    public function orders(int $limit = 10): array
    {
        return $this->request('get', 'account/history_orders/all', ['limit' => $limit]);
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
     * @param string $base
     * @param string $symbols
     * @param int $limit
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function ordersList(string $base, string $symbols, int $limit = 10): array
    {
        return $this->request(
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
     * @return string
     *
     * @throws ConnectorException
     */
    public function createOrder(
        string $side,
        string $symbol,
        float $price,
        ?float $qty = null,
        ?float $amount = null
    ): string {
        if ((null !== $qty && null !== $amount) || (null === $qty && null === $amount)) {
            throw new ConnectorException('You should specify only qty or amount');
        }

        return $this->request('post', sprintf('account/%s/%s', $symbol, $side), [
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
     */
    public function cancelOrder(string $symbol): array
    {
        return $this->request('post', sprintf('account/%s/cancel', $symbol));
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
     */
    private function request(string $method, string $endpoint, array $data = [], array $headers = [])
    {
        if ('auth' !== $endpoint && !$this->authenticated()) {
            throw new ConnectorException('User not specified. Use with()');
        }

        /**
         * @return mixed
         *
         * @throws \RuntimeException
         */
        $makeRequest = function () use ($method, $endpoint, $data, $headers) {
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
            } catch (ClientException | GuzzleException $exception) {
                $response = $exception->getResponse();

                if (!$response) {
                    throw new ConnectorException('Request error: ' . $exception->getMessage());
                }

                $json = \json_decode($contents = $response->getBody()->getContents(), true);

                throw new ConnectorException($json['error'] ?? $exception->getMessage());
            }

            return \json_decode($response, true)['result'];
        };

        try {
            if (null !== $this->cacheTime) {
                $key = $this->cacheKey ?? sha1($method . $endpoint . json_encode($data) . json_encode($headers));
                $response = $this->cached($key, $makeRequest, $this->cacheTime);

                $this->cacheTime = null;
                $this->cacheKey = null;

                return $response;
            }

            return $makeRequest();
        } catch (\Exception | \Throwable | InvalidArgumentException $e) {
            throw new ConnectorException($e->getMessage());
        }
    }

    /**
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     *
     * @return callable|mixed
     *
     * @throws InvalidArgumentException
     */
    private function cached(string $key, callable $callback, int $ttl)
    {
        if (!$this->cache->has($key)) {
            $this->cache->set($key, $result = $callback(), $ttl >= 0 ? $ttl : null);

            return $result;
        }

        return $this->cache->get($key);
    }
}
