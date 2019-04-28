<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

class Symbol
{
    public const STANDARD_FORMAT = '{quote}_{base}';
    public const BITTREX_FORMAT = '{base}-{quote}';
    public const BINANCE_FORMAT = '{quote}{base}';
    public const HUOBI_FORMAT = '_{quote}{base}';

    private $base;
    private $quote;

    /**
     * @param string $standard
     *
     * @return Symbol
     */
    public static function createFromStandard(string $standard): self
    {
        [$quote, $base] = explode('_', $standard);

        return new static($base, $quote);
    }

    /**
     * @param string $base
     * @param string $quote
     */
    public function __construct(string $base, string $quote)
    {
        $this->base = mb_strtoupper($base);
        $this->quote = mb_strtoupper($quote);
    }

    /**
     * @param string $format
     *
     * @return string
     */
    public function format(string $format): string
    {
        $formatted = strtr($format, ['{base}' => $this->base, '{quote}' => $this->quote]);

        if (strpos($format, '_') === 0) {
            return mb_strtolower(substr($formatted, 1));
        }

        return $formatted;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [$this->base, $this->quote];
    }
}
