<?php

namespace App\Domain\Inventory\Data;

final readonly class ReconciliationReport
{
    public function __construct(
        public int $processed,
        public int $updated,
    ) {}
}
