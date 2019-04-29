<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use DateTime;
use Exception;
use MZNX\ExchangeConnector\Connector;
use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\OpenOrder;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Exchange\Bittrex;

class OpenOrderFactory extends OrderFactory
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
        [$base, $quote] = Bittrex::splitMarketName($response['Exchange'])->toArray();

        return new OpenOrder(
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
