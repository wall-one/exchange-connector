<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use DateTime;
use DateTimeInterface;
use Exception;
use MZNX\ExchangeConnector\Connector;
use MZNX\ExchangeConnector\Exchange\Bittrex;
use MZNX\ExchangeConnector\Symbol as ExchangeSymbol;

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
     * @param array $response
     *
     * @return Order
     *
     * @throws Exception
     */
    public static function createFromBittrexResponse(array $response): self
    {
        $symbol = Bittrex::splitMarketName($response['Exchange']);

        return new static(
            $response['OrderUuid'],
            Connector::buildMarketName(...$symbol->toArray()),
            mb_strtoupper(explode('_', $response['OrderType'] ?? $response['Type'])[0]),
            mb_strtoupper(explode('_', $response['OrderType'] ?? $response['Type'])[1]),
            (float)$response['Price'],
            (float)$response['Quantity'],
            (float)$response['Quantity'] - (float)$response['QuantityRemaining'],
            new DateTime($response['TimeStamp'] ?? $response['Closed'])
        );
    }

    /**
     * @param array $response
     * @param ExchangeSymbol $symbol
     *
     * @return Order
     */
    public static function createFromBinanceResponse(array $response, ExchangeSymbol $symbol): self
    {
        return new static(
            (string)$response['orderId'],
            Connector::buildMarketName(...$symbol->toArray()),
            mb_strtoupper($response['type']),
            mb_strtoupper($response['side']),
            (float)$response['price'],
            (float)($response['origQty'] ?? $response['qty']),
            array_key_exists('qty', $response)
                ? (float)$response['qty']
                : (float)$response['executedQty'],
            DateTime::createFromFormat('U',(string)round($response['time'] / 1000))
        );
    }

    /**
     * @param string $id
     * @param string $symbol
     * @param string $type
     * @param string $side
     * @param float $price
     * @param float $qty
     * @param float $filled
     * @param DateTimeInterface $dateTime
     */
    public function __construct(
        string $id,
        string $symbol,
        string $type,
        string $side,
        float $price,
        float $qty,
        float $filled,
        DateTimeInterface $dateTime
    ) {
        $this->id = $id;
        $this->symbol = $symbol;
        $this->type = $type;
        $this->side = $side;
        $this->price = $price;
        $this->qty = $qty;
        $this->filled = $filled;
        $this->dateTime = $dateTime;
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
            'timestamp' => $this->dateTime->getTimestamp()
        ];
    }
}
