<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

class Symbol
{
    public const STANDARD_FORMAT = '{quote}_{base}';
    public const BITTREX_FORMAT = '{base}-{quote}';

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
        return strtr($format, ['{base}' => $this->base, '{quote}' => $this->quote]);
    }
}
