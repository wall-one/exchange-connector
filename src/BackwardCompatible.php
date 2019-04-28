<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

trait BackwardCompatible
{
    /**
     * @param int $ttl
     *
     * @return BackwardCompatible
     */
    public function cache(int $ttl): self
    {
        return $this;
    }

    /**
     * @param Symbol $symbol
     *
     * @return bool
     */
    public function symbolExists(Symbol $symbol): bool
    {
        return in_array($symbol->format(Symbol::STANDARD_FORMAT), array_column($this->symbols(), 'symbol'), true);
    }

    /**
     * @param string $asset
     *
     * @return bool
     */
    public function assetExists(string $asset): bool
    {
        $symbols = array_map('mb_strtoupper', array_column($this->symbols(), 'symbol'));
        $assets = array_merge(...array_map(
            static function (string $symbol) {
                return explode('_', $symbol);
            },
            $symbols
        ));

        return \in_array(mb_strtoupper($asset), $assets, true);
    }
}
