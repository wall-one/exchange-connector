<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

use MZNX\ExchangeConnector\Exchange\Exchange;

/**
 * @deprecated Will be removed in 2.0. Use MZNX\ExchangeConnector\Connector instead
 * @see        Connector
 *
 * @method ExchangeConnector cache(int $ttl)
 * @method bool symbolExists(Symbol $symbol)
 * @method bool assetExists(string $asset)
 *
 * @method string auth(Connection $connection)
 * @method bool authenticated()
 * @method array candles(Symbol $symbol, string $interval, int $limit)
 * @method array wallet()
 * @method array available()
 * @method WaitResponse|array orders(int $limit = 10, ?int $orderId = null)
 * @method array orderInfo(Symbol $symbol, $id)
 * @method array ordersBySymbol(Symbol $symbol, int $limit = 10, ?int $orderId = null)
 * @method string createOrder(string $type, string $side, Symbol $symbol, float $price, float $qty)
 * @method string stopLoss(string $side, Symbol $symbol, float $price, float $qty, float $stopPrice)
 * @method string takeProfit(string $side, Symbol $symbol, float $price, float $qty, float $stopPrice)
 * @method bool cancelOrder(Symbol $symbolOrId) cancelOrder(string $symbolOrId)
 * @method array openOrders(Symbol $symbol)
 * @method array deposits()
 * @method array withdrawals()
 * @method array market(Symbol $symbol, int $depth = 10)
 * @method array symbols()
 */
class ExchangeConnector
{
    private $connector;

    /** @var Connection */
    private $connection;

    /**
     * @param string $base
     * @param string $quote
     *
     * @return string
     */
    public static function buildMarketName(string $base, string $quote): string
    {
        return Connector::buildMarketName($base, $quote);
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
        return Connector::splitMarketName($symbol);
    }

    /**
     * @param string          $exchangeUrl
     * @param Connection|null $connection
     */
    public function __construct(string $exchangeUrl, ?Connection $connection = null)
    {
        $this->connector = new Connector($exchangeUrl);

        if (null !== $connection) {
            $this->with($connection);
        }
    }

    /**
     * @param Connection $connection
     *
     * @return ExchangeConnector
     * @deprecated inject in constructor instead
     *
     */
    public function with(Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return array|mixed
     *
     * @throws ConnectorException
     */
    public function __call($name, $arguments)
    {
        $exchangeApi = $this->getExchangeConnector();

        if (!method_exists($exchangeApi, $name)) {
            throw new ConnectorException(sprintf('Method %s not found in %s', $name, get_class($exchangeApi)));
        }

        return call_user_func_array([$exchangeApi, $name], $arguments);
    }

    /**
     * @return Exchange
     *
     * @throws ConnectorException
     */
    private function getExchangeConnector(): Exchange
    {
        return $this->connector->resolve($this->connection);
    }
}
