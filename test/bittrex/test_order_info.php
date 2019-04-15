<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

/** @var Exchange $connector */

use MZNX\ExchangeConnector\Exchange\Exchange;
use MZNX\ExchangeConnector\Symbol;

$connector = require __DIR__ . '/config.php';

print_r($connector->orderInfo(new Symbol('USDT', 'BTC'), '87520d26-fbad-4473-ba26-dadacddfca65'));