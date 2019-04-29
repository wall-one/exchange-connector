<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use DateTimeInterface;

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
