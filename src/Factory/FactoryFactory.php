<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use MZNX\ExchangeConnector\ConnectorException;
use MZNX\ExchangeConnector\Entity\Deposit;
use MZNX\ExchangeConnector\Entity\OpenOrder;
use MZNX\ExchangeConnector\Entity\Order;
use MZNX\ExchangeConnector\Entity\OrderBookEntry;
use MZNX\ExchangeConnector\Entity\Symbol;
use MZNX\ExchangeConnector\Entity\Withdrawal;

class FactoryFactory
{
    private $exchange;

    /**
     * @param string $exchange
     */
    public function __construct(string $exchange)
    {
        $this->exchange = $exchange;
    }

    /**
     * @param string $class
     *
     * @return Factory
     * @throws ConnectorException
     */
    public function getFactory(string $class): Factory
    {
        static $mapping = [
            Deposit::class => DepositFactory::class,
            OpenOrder::class => OpenOrderFactory::class,
            Order::class => OrderFactory::class,
            OrderBookEntry::class => OrderBookEntryFactory::class,
            Symbol::class => SymbolFactory::class,
            Withdrawal::class => WithdrawalFactory::class
        ];

        if (!array_key_exists($class, $mapping)) {
            throw new ConnectorException('Unknown class ' . $class);
        }

        return new $mapping[$class]($this->exchange);
    }
}
