<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use DateTime;
use Exception;
use MZNX\ExchangeConnector\Connector;
use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Exchange\Bittrex;

class OrderFactory extends AbstractFactory
{
    /**
     * @param array $response
     *
     * @return ArrayConvertible
     *
     * @throws Exception
     */
    protected function createFromBittrexResponse(array $response): ArrayConvertible
    {
        $symbol = Bittrex::splitMarketName($response['Exchange']);

        return new Order(
            $response['OrderUuid'],
            Connector::buildMarketName(...$symbol->toArray()),
            mb_strtoupper(explode('_', $response['OrderType'] ?? $response['Type'])[0]),
            mb_strtoupper(explode('_', $response['OrderType'] ?? $response['Type'])[1]),
            (float)$response['Price'],
            (float)$response['Quantity'],
            (float)$response['Quantity'] - (float)$response['QuantityRemaining'],
            new DateTime($response['TimeStamp'] ?? $response['Closed'])
        );
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromBinanceResponse(array $response): ArrayConvertible
    {
        return new Order(
            (string)$response['orderId'],
            Connector::buildMarketName(...$response['symbol']->toArray()),
            mb_strtoupper($response['type']),
            mb_strtoupper($response['side']),
            (float)$response['price'],
            (float)($response['origQty'] ?? $response['qty']),
            array_key_exists('qty', $response)
                ? (float)$response['qty']
                : (float)$response['executedQty'],
            DateTime::createFromFormat('U',(string)round($response['time'] / 1000))
        );
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromHuobiResponse(array $response): ArrayConvertible
    {
        return new Order(
            (string)$response['id'],
            Connector::buildMarketName(...$response['symbol']->toArray()),
            mb_strtoupper(explode('-', $response['type'])[1]),
            mb_strtoupper(explode('-', $response['type'])[0]),
            (float)($response['price'] ?: $response['field-cash-amount'] / $response['field-amount']),
            (float)$response['amount'],
            (float)($response['field-cash-amount'] / $response['field-amount']),
            DateTime::createFromFormat('U',(string)round($response['finished-at'] / 1000))
        );
    }
}
