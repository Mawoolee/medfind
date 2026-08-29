<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class InsufficientAvailableStock extends DomainException
{
    public function __construct(public readonly int $requested, public readonly int $available)
    {
        parent::__construct("Requested quantity {$requested} exceeds available stock {$available}.");
    }
}
