<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

class OrderBookEntry implements ArrayConvertible
{
    /**
     * @var float
     */
    private $qty;
    /**
     * @var float
     */
    private $price;

    /**
     * @param array $response
     *
     * @return OrderBookEntry
     */
    public static function createFromBittrexResponse(array $response): self
    {
        return new static((float)$response['quantity'], (float)$response['rate']);
    }

    /**
     * @param float $qty
     * @param float $price
     */
    public function __construct(float $qty, float $price)
    {
        $this->qty = $qty;
        $this->price = $price;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'qty' => $this->qty,
            'price' => $this->price
        ];
    }
}
