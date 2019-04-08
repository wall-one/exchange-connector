<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

use MZNX\ExchangeConnector\Exchange\Bittrex;
use MZNX\ExchangeConnector\Exchange\DefaultExchange;
use MZNX\ExchangeConnector\Exchange\Exchange;

class Connector
{
    public const DELIMITER = '_';

    private $connectorUrl;

    /**
     * @param string $base
     * @param string $quote
     *
     * @return string
     */
    public static function buildMarketName(string $base, string $quote): string
    {
        return mb_strtoupper(sprintf('%s%s%s', $quote, static::DELIMITER, $base));
    }

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
     * @param string $connectorUrl
     */
    public function __construct(string $connectorUrl)
    {
        $this->connectorUrl = $connectorUrl;
    }

    /**
     * @param Connection $connection
     *
     * @return Exchange
     *
     * @throws ConnectorException
     */
    public function resolve(Connection $connection): Exchange
    {
        switch (mb_strtolower($connection->getExchange())) {
            case 'bittrex':
                return new Bittrex($connection);

            default:
                return new DefaultExchange($this->connectorUrl, $connection);
        }
    }
}
