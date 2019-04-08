<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

use MZNX\ExchangeConnector\Connector;
use MZNX\ExchangeConnector\Exchange\Bittrex;

class Symbol implements ArrayConvertible
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
    private $base;
    /**
     * @var string
     */
    private $quote;
    /**
     * @var float
     */
    private $basePrecision;
    /**
     * @var float
     */
    private $quotePrecision;
    /**
     * @var float
     */
    private $step;
    /**
     * @var float
     */
    private $minQty;
    /**
     * @var float
     */
    private $minAmount;

    /**
     * @param array $response
     *
     * @return Symbol
     */
    public static function createFromBittrexResponse(array $response): self
    {
        [$base, $quote] = Bittrex::splitMarketName($response['MarketName']);

        return new static(
            $response['MarketName'],
            Connector::buildMarketName($base, $quote),
            $response['BaseCurrency'],
            $response['MarketCurrency'],
            8,
            8,
            0,
            (float)$response['MinTradeSize']
        );
    }

    /**
     * @param string $id
     * @param string $symbol
     * @param string $base
     * @param string $quote
     * @param float $basePrecision
     * @param float $quotePrecision
     * @param float $step
     * @param float $minQty
     * @param float $minAmount
     */
    public function __construct(
        string $id,
        string $symbol,
        string $base,
        string $quote,
        float $basePrecision,
        float $quotePrecision,
        float $step,
        float $minQty,
        float $minAmount = 0.
    ) {
        $this->id = $id;
        $this->symbol = $symbol;
        $this->base = $base;
        $this->quote = $quote;
        $this->basePrecision = $basePrecision;
        $this->quotePrecision = $quotePrecision;
        $this->step = $step;
        $this->minQty = $minQty;
        $this->minAmount = $minAmount;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'base' => $this->base,
            'quote' => $this->quote,
            'base_precision' => $this->basePrecision,
            'quote_precision' => $this->quotePrecision,
            'step' => $this->step,
            'min_qty' => $this->minQty,
            'min_amount' => $this->minAmount
        ];
    }
}
