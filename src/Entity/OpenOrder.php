<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use DateTime;
use Exception;
use MZNX\ExchangeConnector\Connector;
use MZNX\ExchangeConnector\Exchange\Bittrex;

class OpenOrder extends Order
{
    /**
     * @param array $response
     *
     * @return Order
     *
     * @throws Exception
     */
    public static function createFromBittrexResponse(array $response): Order
    {
        [$base, $quote] = Bittrex::splitMarketName($response['Exchange']);

        return new static(
            $response['OrderUuid'],
            Connector::buildMarketName($base, $quote),
            mb_strtoupper($response['OrderType']),
            mb_strtoupper(explode('_', $response['OrderType'])[1]),
            (float)$response['Price'],
            (float)$response['Quantity'],
            (float)$response['Quantity'] - (float)$response['QuantityRemaining'],
            new DateTime($response['Opened'])
        );
    }
}
