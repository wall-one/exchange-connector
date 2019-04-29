<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

use MZNX\ExchangeConnector\Exchange\Bittrex;
use MZNX\ExchangeConnector\Exchange\Binance;
use MZNX\ExchangeConnector\Exchange\DefaultExchange;
use MZNX\ExchangeConnector\Exchange\Exchange;
use MZNX\ExchangeConnector\Exchange\Huobi;

class Connector
{
    private $connectorUrl;

    /**
     * @param string $base
     * @param string $quote
     *
     * @return string
     */
    public static function buildMarketName(string $base, string $quote): string
    {
        return mb_strtoupper((new Symbol($base, $quote))->format('{quote}_{base}'));
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
        return Symbol::createFromStandard($symbol)->toArray();
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
        static $mapping = [
            Bittrex::LABEL => Bittrex::class,
            Binance::LABEL => Binance::class,
            Huobi::LABEL => Huobi::class,
            Huobi::LABEL_RU => Huobi::class,
            Huobi::LABEL_EN => Huobi::class,
            Huobi::LABEL_CH => Huobi::class,
        ];

        $exchange = mb_strtolower($connection->getExchange());

        if ($exchange !== 'huobi' && 0 === strpos($exchange, 'huobi')) {
            $connection->setCustomerId(explode('_', $exchange)[1]);
        }

        if (array_key_exists($exchange, $mapping)) {
            return new $mapping[$exchange]($connection);
        }

        return new DefaultExchange($this->connectorUrl, $connection);
    }
}
