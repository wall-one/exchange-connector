<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use DateTime;
use Exception;
use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\Withdrawal;

class WithdrawalFactory extends AbstractFactory
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
        $status = 'success';

        if ($response['Opened']) {
            $status = 'pending';
        } elseif ($response['Canceled']) {
            $status = 'canceled';
        }

        return new Withdrawal(
            (float)$response['Amount'],
            $response['Address'],
            '',
            mb_strtoupper($response['Currency']),
            $response['TxId'],
            $response['Opened'] ? new DateTime($response['Opened']) : null,
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
        switch ($response['status']) {
            case 1:
                $status = 'canceled';
                break;

            case 5:
                $status = 'failed';
                break;

            case 6:
                $status = 'success';
                break;

            default:
                $status = 'pending';
                break;
        }

        return new Withdrawal(
            (float)$response['amount'],
            $response['address'],
            $response['addressTag'],
            mb_strtoupper($response['asset']),
            $response['txId'],
            DateTime::createFromFormat('U', (string)round($response['applyTime'] / 1000)),
            $status
        );
    }

    /**
     * @param array $response
     *
     * @return ArrayConvertible
     */
    protected function createFromHuobiResponse(array $response): ArrayConvertible
    {
        return new Withdrawal(
            (float)$response['amount'],
            mb_strtoupper($response['currency']),
            $response['address'],
            $response['address-tag'],
            $response['tx-hash'],
            DateTime::createFromFormat('U', (string)round($response['updated-at'] / 1000)),
            $response['state'] === 'confirmed' ? 'success' : 'pending'
        );
    }
}
