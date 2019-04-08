<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Exchange;

use MZNX\ExchangeConnector\Connection;
use MZNX\ExchangeConnector\WaitResponse;

interface Exchange
{
    /**
     * Returns array [$base, $quote]
     *
     * @param string $symbol
     *
     * @return array
     */
    public static function splitMarketName(string $symbol): array;

    /**
     * @param Connection $connection
     *
     * @return string
     */
    public function auth(Connection $connection): string;

    /**
     * @param Connection $connection
     *
     * @return Exchange
     */
    public function with(Connection $connection): Exchange;

    /**
     * @return bool
     */
    public function authenticated(): bool;

    /**
     * @deprecated
     *
     * @param string $symbol
     * @param string $interval
     * @param int $limit
     *
     * @return array
     */
    public function candles(string $symbol, string $interval, int $limit): array;

    /**
     * @return array
     */
    public function wallet(): array;

    /**
     * @param int $limit
     *
     * @return WaitResponse|array
     */
    public function orders(int $limit = 10);

    /**
     * @param string $symbol
     * @param $id
     *
     * @return array
     */
    public function orderInfo(string $symbol, $id): array;

    /**
     * @param string $symbol
     * @param int $limit
     *
     * @return array
     */
    public function ordersBySymbol(string $symbol, int $limit = 10): array;

    /**
     * @param string $side
     * @param string $symbol
     * @param float $price
     * @param float $qty
     *
     * @return string
     */
    public function createOrder(string $side, string $symbol, float $price, float $qty): string;

    /**
     * @param string|int $symbolOrId
     *
     * @return bool
     */
    public function cancelOrder($symbolOrId): bool;

    /**
     * @param string $symbol
     *
     * @return array
     */
    public function openOrders(string $symbol): array;

    /**
     * @return array
     */
    public function deposits(): array;

    /**
     * @return array
     */
    public function withdrawals(): array;

    /**
     * @param string $market
     * @param int $depth
     *
     * @return array
     */
    public function market(string $market, int $depth = 10): array;

    /**
     * @return array
     */
    public function symbols(): array;
}
