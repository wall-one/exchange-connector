<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

class Connection
{
    /** @var string */
    private $exchange;
    /** @var string */
    private $apiKey;
    /** @var string */
    private $secretKey;
    /** @var null|string */
    private $customerId;

    /**
     * @param string $exchange
     * @param string $apiKey
     * @param string $secretKey
     * @param string|null $customerId
     *
     * @throws ConnectorException
     */
    public function __construct(string $exchange, string $apiKey, string $secretKey, ?string $customerId = null)
    {
        $this->exchange = mb_strtolower($exchange);
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->customerId = $customerId;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'exchange' => $this->exchange,
            'api_key' => $this->apiKey,
            'secret_key' => $this->secretKey,
            'customer_id' => $this->customerId
        ];
    }

    /**
     * array
     *      ['api_key']         string Api key of exchange,
     *      ['customer_id']     mixed Should be null
     *      ['exchange']        string (optional) bittrex, kraken, bitstamp, binance
     *      ['secret_key']      string Secret key of exchange
     *
     * @param array $array
     *
     * @return Connection
     *
     * @throws ConnectorException
     */
    public static function create(array $array): self
    {
        foreach (['api_key', 'customer_id', 'secret_key'] as $field) {
            if (!array_key_exists($field, $array)) {
                throw new ConnectorException("$field is not specfied");
            }
        }

        return new static($array['exchange'], $array['api_key'], $array['secret_key'], $array['customer_id'] ?? null);
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return string
     */
    public function getExchange(): string
    {
        return $this->exchange;
    }

    /**
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }
}
