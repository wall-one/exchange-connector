<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use DateTime;
use Exception;
use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\Deposit;

class DepositFactory extends AbstractFactory
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
        return new Deposit(
            (float)$response['Amount'],
            mb_strtolower($response['Currency']),
            $response['CryptoAddress'],
            '',
            $response['TxId'],
            $response['Confirmations'] > 3 ? 'success' : 'pending',
            new DateTime($response['LastUpdated'])
        );
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromBinanceResponse(array $response): ArrayConvertible
    {
        return new Deposit(
            (float)$response['amount'],
            mb_strtolower($response['asset']),
            $response['address'],
            $response['addressTag'],
            $response['txId'],
            $response['status'] === 1 ? 'success' : 'pending',
            DateTime::createFromFormat('U', (string)round($response['insertTime'] / 1000))
        );
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromHuobiResponse(array $response): ArrayConvertible
    {
        return new Deposit(
            (float)$response['amount'],
            mb_strtolower($response['currency']),
            $response['address'],
            $response['address-tag'],
            $response['tx-hash'],
            $response['status'] === 'safe' ? 'success' : 'pending',
            DateTime::createFromFormat('U', (string)round($response['updated-at'] / 1000))
        );
    }
}
