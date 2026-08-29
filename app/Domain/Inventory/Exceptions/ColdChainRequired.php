<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class ColdChainRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('This medicine requires cold-chain storage for every received batch.');
    }
}
