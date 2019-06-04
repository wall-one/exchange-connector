<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use DateTime;
use Exception;
use MZNX\ExchangeConnector\Connector;
use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Exchange\Bittrex;
use MZNX\ExchangeConnector\Exchange\Okex;

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

        $status = 'FILLED';

        if ( isset($response['IsOpen']) ) {
            if ( $response['QuantityRemaining'] != 0 ) {
                $status = 'OPENED';
            }
        } else {
            if ( $response['QuantityRemaining'] == 0 ) {
                $status = 'FILLED';
            } else {
                $status = 'CANCELED';
            }
        }

        return new Order(
            $response['OrderUuid'],
            Connector::buildMarketName(...$symbol->toArray()),
            mb_strtoupper(explode('_', $response['OrderType'] ?? $response['Type'])[0]),
            mb_strtoupper(explode('_', $response['OrderType'] ?? $response['Type'])[1]),
            (float)$response['PricePerUnit'],
            (float)$response['Quantity'],
            (float)$response['Quantity'] - (float)$response['QuantityRemaining'],
            new DateTime($response['TimeStamp'] ?? $response['Closed']),
            $status
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
            DateTime::createFromFormat('U',(string)round($response['time'] / 1000)),
            $response['status']
        );
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromHuobiResponse(array $response): ArrayConvertible
    {
        if ( $response['field-amount'] != 0 ) {
            $price = $response['field-cash-amount'] / $response['field-amount'];
        } else {
            $price = 0;
        }

        return new Order(
            (string)$response['id'],
            Connector::buildMarketName(...$response['symbol']->toArray()),
            mb_strtoupper(explode('-', $response['type'])[1]),
            mb_strtoupper(explode('-', $response['type'])[0]),
            (float)($response['price'] == 0 ? 0 : $price),
            (float)$response['amount'],
            (float)$response['field-amount'],
            DateTime::createFromFormat('U',(string)round($response['finished-at'] / 1000)),
            mb_strtoupper( $response['state'] )
        );
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     * @throws Exception
     */
    protected function createFromOkexResponse(array $response): ArrayConvertible
    {
        $symbol = Okex::splitMarketName($response['instrument_id']);

        return new Order(
            $response['order_id'],
            Connector::buildMarketName(...$symbol->toArray()),
            mb_strtoupper($response['type']),
            mb_strtoupper($response['side']),
            (float)($response['price'] ?: $response['notional']),
            (float)$response['size'] ?: (float)$response['filled_size'],
            (float)$response['filled_size'],
            new DateTime($response['timestamp'])
        );
    }
}
