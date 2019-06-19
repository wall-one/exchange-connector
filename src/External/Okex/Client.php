<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\External\Okex;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use MZNX\ExchangeConnector\ConnectorException;

class Client
{
    private const GET = 'GET';
    private const POST = 'POST';

    public const SELL = 'sell';
    public const BUY = 'buy';

    public const LIMIT = 'limit';
    public const MARKET = 'market';

    private $apiKey;
    private $secretKey;
    private $passPhrase;

    public function __construct(string $apiKey, string $secretKey, string $passPhrase)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->passPhrase = $passPhrase;
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function walletInfo(): array
    {
        return $this->request(self::GET, '/account/v3/wallet');
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function instruments(): array
    {
        return $this->request(self::GET, '/spot/v3/instruments');
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function deposits(): array
    {
        return $this->request(self::GET, '/account/v3/deposit/history');
    }

    /**
     * @return array
     *
     * @throws ConnectorException
     */
    public function withdrawals(): array
    {
        return $this->request(self::GET, '/account/v3/withdrawal/history');
    }

    /**
     * @param string      $symbol
     * @param string|null $from
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function orders(string $symbol, ?string $from = null): array
    {
        return $this->request(self::GET, '/spot/v3/orders', array_merge(
            [
                'instrument_id' => $symbol,
                'state' => 7
            ],
            $from ? ['from' => $from] : []
        ));
    }

    /**
     * @param string      $symbol
     * @param string|null $from
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function openOrders(string $symbol, ?string $from = null): array
    {
        return $this->request(self::GET, '/spot/v3/orders_pending', array_merge(
            [
                'instrument_id' => $symbol,
            ],
            $from ? ['from' => $from] : []
        ));
    }

    /**
     * @param string     $type
     * @param string     $side
     * @param string     $symbol
     * @param float      $qty
     * @param float|null $price
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function placeOrder(string $type, string $side, string $symbol, float $qty, ?float $price = null): array
    {
        return $this->request(self::POST, '/spot/v3/orders', array_filter([
            'type' => strtolower($type),
            'side' => strtolower($side),
            'instrument_id' => $symbol,
            'margin_trading' => 1,
            'order_type' => 0,
            'price' => $price,
            'size' => $qty
        ]));
    }

    /**
     * @param string $id
     * @param string $symbol
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function cancelOrder(string $id, string $symbol): array
    {
        return $this->request(self::POST, '/spot/v3/cancel_orders/' . $id, [
            'instrument_id' => $symbol
        ]);
    }

    /**
     * @param string $symbol
     * @param string $id
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function orderInfo(string $symbol, string $id): array
    {
        return $this->request(self::GET, '/spot/v3/orders/' . $id, [
            'instrument_id' => $symbol,
        ]);
    }

    /**
     * @param int    $size
     * @param string $symbol
     *
     * @return array
     *
     * @throws ConnectorException
     */
    public function orderBook(int $size, string $symbol): array
    {
        if ($size > 200 || $size < 10) {
            throw new ConnectorException('Invalid size');
        }

        return $this->request(self::GET, sprintf('/spot/v3/instruments/%s/book', $symbol), [
            'size' => $size,
            'depth' => 0.001
        ]);
    }

    /**
     * @param string $timestamp
     * @param string $type
     * @param string $path
     * @param array  $body
     *
     * @return string
     */
    private function sign(string $timestamp, string $type, string $path, array $body = []): string
    {
        $bodyStr = '';

        if ($body) {
            $bodyStr = self::GET === strtoupper($type) ? '?' . http_build_query($body) : json_encode($body);
        }

        $data = $timestamp . strtoupper($type) . '/api' . $path . ($bodyStr ?: '');

        return base64_encode(hash_hmac('sha256', $data, $this->secretKey, true));
    }

    /**
     * @param string $type
     * @param string $path
     * @param array  $content
     *
     * @return array
     *
     * @throws ConnectorException
     */
    private function request(string $type, string $path, array $content = []): array
    {
        try {
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://www.okex.com']);

            $contentParam = self::GET === strtoupper($type) ? 'query' : 'json';
            $timestamp = gmdate('Y-m-d\TH:i:s\.000\Z');

            try {
                $response = $client->request(strtoupper($type), '/api' . $path, [
                    $contentParam => $content,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'OK-ACCESS-KEY' => $this->apiKey,
                        'OK-ACCESS-TIMESTAMP' => $timestamp,
                        'OK-ACCESS-PASSPHRASE' => $this->passPhrase,
                        'OK-ACCESS-SIGN' => $this->sign($timestamp, $type, $path, $content)
                    ],

                ]);
            } catch (RequestException | GuzzleException $e) {
                $error = [];

                if ($e instanceof RequestException) {
                    $error = json_decode($e->getResponse()->getBody()->getContents(), true);
                }

                throw new ConnectorException($error['message'] ?? 'Unexpected error');
            }

            $response = json_decode($response->getBody()->getContents(), true) ?? [];

            if (!($response['result'] ?? true)) {
                $message = null;

                if (array_key_exists('error_code', $response)) {
                    $message .= "[{$response['error_code']}] ";
                }

                if (array_key_exists('error_message', $response)) {
                    $message .= $response['error_message'];
                } else {
                    $message .= 'Something wrong';
                }

                throw new ConnectorException($message);
            }

            return $response;
        } catch (Exception $e) {
            if ($e instanceof ConnectorException) {
                throw $e;
            }

            throw new ConnectorException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
