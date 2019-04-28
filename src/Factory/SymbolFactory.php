<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use MZNX\ExchangeConnector\Connector;
use MZNX\ExchangeConnector\Entity\ArrayConvertible;
use MZNX\ExchangeConnector\Entity\Symbol;
use MZNX\ExchangeConnector\Exchange\Bittrex;

class SymbolFactory extends AbstractFactory
{
    /**
     * @param array $response
     *
     * @return Symbol
     */
    protected function createFromBittrexResponse(array $response): ArrayConvertible
    {
        [$base, $quote] = Bittrex::splitMarketName($response['MarketName'])->toArray();

        return new Symbol(
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
     * @param array $response
     *
     * @return Symbol
     */
    protected function createFromBinanceResponse(array $response): ArrayConvertible
    {
        $step = null;
        $minQty = null;
        $minAmount = null;

        foreach ($response['filters'] as $filter) {
            if ($filter['filterType'] === 'LOT_SIZE') {
                $minQty = (float)$filter['minQty'];
            }

            if ($filter['filterType'] === 'LOT_SIZE') {
                $step = (float)$filter['stepSize'];
            }

            if ($filter['filterType'] === 'PRICE_FILTER') {
                $tick = (float)$filter['tickSize'];
            }

            if ($filter['filterType'] === 'MIN_NOTIONAL') {
                $minAmount = (float)$filter['minNotional'];
            }
        }

        return new Symbol(
            $response['symbol'],
            Connector::buildMarketName($response['quoteAsset'], $response['baseAsset']),
            $response['quoteAsset'],
            $response['baseAsset'],
            $response['quotePrecision'],
            $response['baseAssetPrecision'],
            $step ?? 0.,
            $minQty ?? 0.001,
            $minAmount ?? 0.001,
            $tick ?? 0.01
        );
    }

    /**
     * @param array $response
     *
     * @return Symbol
     */
    protected function createFromHuobiResponse(array $response): ArrayConvertible
    {
        return new Symbol(
            $response['symbol'],
            Connector::buildMarketName($response['quote-currency'], $response['base-currency']),
            $response['quote-currency'],
            $response['base-currency'],
            $response['amount-precision'],
            $response['price-precision'],
            0.,
            0.00001
        );
    }
}
