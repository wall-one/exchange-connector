<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

/** @var Exchange $connector */

use MZNX\ExchangeConnector\Exchange\Exchange;
use MZNX\ExchangeConnector\Symbol;

$connector = require __DIR__ . '/config.php';

$price = $connector->market($symbol = new Symbol('USDT', 'BTC'))['asks'][0]['price'] + 20;

$uuid = $connector->createOrder('SELL', $symbol, $price, round(30 / $price, 6));

print_r($uuid);
print_r($connector->openOrders($symbol));
print_r($connector->cancelOrder($symbol));