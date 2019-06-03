<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use DateTimeInterface;

class Order implements ArrayConvertible
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $symbol;
    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $side;
    /**
     * @var float
     */
    private $price;
    /**
     * @var float
     */
    private $qty;
    /**
     * @var float
     */
    private $filled;
    /**
     * @var DateTimeInterface
     */
    private $dateTime;

    /**
     * @var string|null
     */
    private $status;

    /**
     * @param string $id
     * @param string $symbol
     * @param string $type
     * @param string $side
     * @param float $price
     * @param float $qty
     * @param float $filled
     * @param DateTimeInterface $dateTime
     * @param string|null $status
     */
    public function __construct(
        string $id,
        string $symbol,
        string $type,
        string $side,
        float $price,
        float $qty,
        float $filled,
        DateTimeInterface $dateTime,
        ?string $status = null
    ) {
        $this->id = $id;
        $this->symbol = $symbol;
        $this->type = $type;
        $this->side = $side;
        $this->price = $price;
        $this->qty = $qty;
        $this->filled = $filled;
        $this->dateTime = $dateTime;
        $this->status = $status;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'type' => $this->type,
            'side' => $this->side,
            'price' => $this->price,
            'qty' => $this->qty,
            'filled' => $this->filled,
            'status' => $this->status,
            'timestamp' => $this->dateTime->getTimestamp()
        ];
    }
}
