<?php
declare(strict_types=1);

namespace MZNX\ExchangeConnector\Entity;

interface ArrayConvertible
{
    public function toArray(): array;
}
