<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use DateTimeInterface;

class Withdrawal implements ArrayConvertible
{
    /**
     * @var float
     */
    private $amount;
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
    private $asset;
    /**
     * @var string
     */
    private $txId;
    /**
     * @var DateTimeInterface
     */
    private $applyTime;
    /**
     * @var string
     */
    private $status;

    /**
     * @param float $amount
     * @param string $address
     * @param string $addressTag
     * @param string $asset
     * @param string $txId
     * @param DateTimeInterface|null $applyTime
     * @param string $status
     */
    public function __construct(
        float $amount,
        string $address,
        string $addressTag,
        string $asset,
        string $txId,
        ?DateTimeInterface $applyTime,
        string $status
    ) {
        $this->amount = $amount;
        $this->address = $address;
        $this->addressTag = $addressTag;
        $this->asset = $asset;
        $this->txId = $txId;
        $this->applyTime = $applyTime;
        $this->status = $status;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'address' => $this->address,
            'address_tag' => $this->addressTag,
            'asset' => $this->asset,
            'tx_id' => $this->txId,
            'apply_time' => $this->applyTime ? $this->applyTime->getTimestamp() * 1000 : null,
            'status' => $this->status,
        ];
    }
}
