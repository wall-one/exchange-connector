<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use DateTime;
use DateTimeInterface;
use Exception;

class Deposit implements ArrayConvertible
{
    /**
     * @var float
     */
    private $amount;
    /**
     * @var string
     */
    private $asset;
    /**
     * @var string
     */
    private $address;
    /**
     * @var string
     */
    private $addressTag;
    /**
     * @var string
     */
    private $txId;
    /**
     * @var string
     */
    private $status;
    /**
     * @var DateTimeInterface
     */
    private $insertTime;

    /**
     * @param array $response
     *
     * @return Deposit
     *
     * @throws Exception
     */
    public static function createFromBittrexResponse(array $response): self
    {
        return new static(
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
     * @return Deposit
     */
    public static function createFromBinanceResponse(array $response): self
    {
        return new static(
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
     * @param float $amount
     * @param string $asset
     * @param string $address
     * @param string $addressTag
     * @param string $txId
     * @param string $status
     * @param DateTimeInterface $insertTime
     */
    public function __construct(
        float $amount,
        string $asset,
        string $address,
        string $addressTag,
        string $txId,
        string $status,
        DateTimeInterface $insertTime
    ) {
        $this->amount = $amount;
        $this->asset = $asset;
        $this->address = $address;
        $this->addressTag = $addressTag;
        $this->txId = $txId;
        $this->status = $status;
        $this->insertTime = $insertTime;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'asset' => $this->asset,
            'address' => $this->address,
            'address_tag' => $this->addressTag,
            'tx_id' => $this->txId,
            'status' => $this->status,
            'insert_time' => $this->insertTime->getTimestamp() * 1000,
        ];
    }
}
