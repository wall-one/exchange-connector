<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

class Candle implements ArrayConvertible
{
    /**
     * @var int
     */
    private $openTime;
    /**
     * @var int
     */
    private $closeTime;
    /**
     * @var float
     */
    private $open;
    /**
     * @var float
     */
    private $high;
    /**
     * @var float
     */
    private $low;
    /**
     * @var float
     */
    private $close;
    /**
     * @var float
     */
    private $volume;
    /**
     * @var float
     */
    private $assetVolume;
    /**
     * @var int
     */
    private $trades;
    /**
     * @var float
     */
    private $assetBuyVolume;
    /**
     * @var float
     */
    private $takerBuyVolume;

    /**
     * @param array $response
     *
     * @return Candle
     */
    public static function createFromBinanceResponse(array $response): self
    {
        return new static(
            (int)$response['openTime'],
            (int)$response['closeTime'],
            (float)$response['open'],
            (float)$response['high'],
            (float)$response['low'],
            (float)$response['close'],
            (float)$response['volume'],
            (float)$response['assetVolume'],
            (int)$response['trades'],
            (float)$response['assetBuyVolume'],
            (float)$response['takerBuyVolume']
        );
    }

    /**
     * @param int $openTime
     * @param int $closeTime
     * @param float $open
     * @param float $high
     * @param float $low
     * @param float $close
     * @param float $volume
     * @param float $assetVolume
     * @param int $trades
     * @param float $assetBuyVolume
     * @param float $takerBuyVolume
     */
    public function __construct(
        int $openTime,
        int $closeTime,
        float $open,
        float $high,
        float $low,
        float $close,
        float $volume,
        float $assetVolume,
        int $trades,
        float $assetBuyVolume,
        float $takerBuyVolume
    ) {
        $this->openTime = $openTime;
        $this->closeTime = $closeTime;
        $this->open = $open;
        $this->high = $high;
        $this->low = $low;
        $this->close = $close;
        $this->volume = $volume;
        $this->assetVolume = $assetVolume;
        $this->trades = $trades;
        $this->assetBuyVolume = $assetBuyVolume;
        $this->takerBuyVolume = $takerBuyVolume;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'open_time' => $this->openTime,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
            'close_time' => $this->closeTime,
            'quote_asset_volume' => $this->assetVolume,
            'number_of_trades' => $this->trades,
            'taker_buy_base_asset_volume' => $this->assetBuyVolume,
            'taker_buy_quote_asset_volume' => $this->takerBuyVolume,
        ];
    }
}
