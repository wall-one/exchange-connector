<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector;

use MZNX\ExchangeConnector\Factory\FactoryFactory;

trait Authenticable
{
    /** @var FactoryFactory */
    private $factory;

    /**
     * @param Connection|null $connection
     */
    public function __construct(?Connection $connection = null)
    {
        $this->factory = new FactoryFactory(static::LABEL);

        if (null !== $connection) {
            $this->with($connection);
        }
    }

    /**
     * @param Connection $connection
     *
     * @return string
     */
    public function auth(Connection $connection): string
    {
        return base64_encode(json_encode($connection->toArray()));
    }

    /**
     * @return bool
     */
    public function authenticated(): bool
    {
        return null !== $this->connection && null !== $this->client;
    }
}
