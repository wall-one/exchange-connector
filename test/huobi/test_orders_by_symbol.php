<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

/** @var Exchange $connector */

use MZNX\ExchangeConnector\Exchange\Exchange;
use MZNX\ExchangeConnector\Symbol;

$connector = require __DIR__ . '/config.php';

print_r($connector->ordersBySymbol(new Symbol('USDT', 'ETH'), 10, 0));