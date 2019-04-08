<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use DateTime;
use Exception;

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
     * @var DateTime
     */
    private $applyTime;
    /**
     * @var string
     */
    private $status;

    /**
     * @param array $response
     *
     * @return Withdrawal
     *
     * @throws Exception
     */
    public static function createFromBittrexResponse(array $response): self
    {
        $status = 'Completed';

        if ($response['Opened']) {
            $status = 'Pending';
        } elseif ($response['Canceled']) {
            $status = 'Canceled';
        }

        return new static(
            (float)$response['Amount'],
            $response['Address'],
            '',
            mb_strtolower($response['Currency']),
            $response['TxId'],
            $response['Opened'] ? new DateTime($response['Opened']) : null,
            $status
        );
    }

    /**
     * @param float $amount
     * @param string $address
     * @param string $addressTag
     * @param string $asset
     * @param string $txId
     * @param DateTime|null $applyTime
     * @param string $status
     */
    public function __construct(
        float $amount,
        string $address,
        string $addressTag,
        string $asset,
        string $txId,
        ?DateTime $applyTime,
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
