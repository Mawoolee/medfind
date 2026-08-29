<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class DuplicateBatchIdentity extends DomainException
{
    public function __construct(public readonly string $batchNumber, public readonly ?string $lotNumber = null)
    {
        parent::__construct('A batch with the same batch and lot identity already exists for this medicine.');
    }
}
