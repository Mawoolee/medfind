<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class UntraceableStockIncrease extends DomainException
{
    public function __construct()
    {
        parent::__construct('A stock increase requires complete adjustment batch metadata.');
    }
}
