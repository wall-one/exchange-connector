<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Factory;

use MZNX\ExchangeConnector\Entity\ArrayConvertible;

interface Factory
{
    public function createFromResponse(array $response): ArrayConvertible;
}
