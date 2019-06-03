<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\OrderBookEntry;

class OrderBookEntryFactory extends AbstractFactory
{
    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromBittrexResponse(array $response): ArrayConvertible
    {
        return new OrderBookEntry((float)$response['Quantity'], (float)$response['Rate']);
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromBinanceResponse(array $response): ArrayConvertible
    {
        return new OrderBookEntry(...array_reverse($response));
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromHuobiResponse(array $response): ArrayConvertible
    {
        return new OrderBookEntry(...array_reverse($response));
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromOkexResponse(array $response): ArrayConvertible
    {
        return new OrderBookEntry((float)$response[1], (float)$response[0]);
    }
}
