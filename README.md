# Cryptocurrency exchanges connector
Universal connector for Binance, Bittrex, Huobi and Okex APIs

## Supported exchanges
* Binance
* Bittrex
* Huobi
* Okex

## How to connect
Use Composer to import package in your project.
`composer require mazanax/exchange-connector`

## How to use
Have to choose exchange to connect and make `Connection` object with needed settings. After that, use `resolve` to make connector.

```php
$connector = (new Connector(''))->resolve(new Connection(
    'binance',
    'API_KEY',
    'API_SECRET'
));

print_r($connector->orders());

```

You can find more examples in `tests` folder.
