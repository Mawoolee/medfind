<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;
use Throwable;

final class SaleLineInsufficientStock extends DomainException
{
    public function __construct(
        public readonly int $lineIndex,
        public readonly int $inventoryItemId,
        public readonly string $medicineName,
        public readonly int $requested,
        public readonly int $available,
        ?Throwable $previous = null,
    ) {
        $lineNumber = $lineIndex + 1;

        parent::__construct(
            "Sale line {$lineNumber} ({$medicineName}) requested {$requested}, but only {$available} is currently available.",
            0,
            $previous,
        );
    }
}
