<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

/** @var Exchange $connector */

use MZNX\ExchangeConnector\Exchange\Exchange;
use MZNX\ExchangeConnector\OrderTypes;
use MZNX\ExchangeConnector\Symbol;

$connector = require __DIR__ . '/config.php';

$balance = $connector->wallet();
$price = $connector->market($symbol = new Symbol('USDT', 'BTC'))['asks'][0]['price'];
//$uuid = $connector->createOrder(OrderTypes::LIMIT, 'BUY', $symbol, $price, $balance['USDT'] / $price);

//print_r($uuid);
//print_r($connector->openOrders($symbol));
print_r($connector->cancelOrder($symbol));