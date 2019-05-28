<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

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
     * @var
     */
    private $tick;

    /**
     * @var float
     */
    private $minQty;
    /**
     * @var float
     */
    private $minAmount;

    /**
     * @param string $id
     * @param string $symbol
     * @param string $base
     * @param string $quote
     * @param int    $basePrecision
     * @param int    $quotePrecision
     * @param float  $step
     * @param float  $minQty
     * @param float  $minAmount
     * @param float  $tick
     */
    public function __construct(
        string $id,
        string $symbol,
        string $base,
        string $quote,
        int $basePrecision,
        int $quotePrecision,
        float $step,
        float $minQty,
        float $minAmount = 0.,
        float $tick = 0.
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
        $this->tick = $tick;
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
            'tick' => $this->tick,
            'min_qty' => $this->minQty,
            'min_amount' => $this->minAmount
        ];
    }
}
