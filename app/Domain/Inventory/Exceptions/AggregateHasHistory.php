<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class AggregateHasHistory extends DomainException
{
    public function __construct()
    {
        parent::__construct('Inventory with batch, audit, or operational history cannot be deleted.');
    }
}
