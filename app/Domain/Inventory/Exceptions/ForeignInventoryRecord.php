<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class ForeignInventoryRecord extends DomainException
{
    public function __construct()
    {
        parent::__construct('The requested inventory record was not found.');
    }
}
