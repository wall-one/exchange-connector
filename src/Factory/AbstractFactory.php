<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use MZNX\ExchangeConnector\ConnectorException;
use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\Symbol;
use MZNX\ExchangeConnector\Exchange\Binance;
use MZNX\ExchangeConnector\Exchange\Bittrex;
use MZNX\ExchangeConnector\Exchange\Huobi;
use MZNX\ExchangeConnector\Exchange\Okex;

abstract  class AbstractFactory implements Factory
{
    /** @var string */
    protected $exchange;

    /**
     * @param string $exchange
     */
    public function __construct(string $exchange)
    {
        $this->exchange = $exchange;
    }

    /**
     * @param array $response
     *
     * @return Symbol
     *
     * @throws ConnectorException
     */
    public function createFromResponse(array $response): ArrayConvertible
    {
        switch ($this->exchange) {
            case Bittrex::LABEL:
                return $this->createFromBittrexResponse($response);

            case Binance::LABEL:
                return $this->createFromBinanceResponse($response);

            case Huobi::LABEL:
                return $this->createFromHuobiResponse($response);

            case Okex::LABEL:
                return $this->createFromOkexResponse($response);
        }

        throw new ConnectorException('Unknown exchange ' . $this->exchange);
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    abstract protected function createFromBittrexResponse(array $response): ArrayConvertible;

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    abstract protected function createFromBinanceResponse(array $response): ArrayConvertible;

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    abstract protected function createFromHuobiResponse(array $response): ArrayConvertible;

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    abstract protected function createFromOkexResponse(array $response): ArrayConvertible;
}
