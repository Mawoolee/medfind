<?php

namespace App\Domain\Inventory\Data;

use App\Models\InventoryBatch;
use InvalidArgumentException;

final readonly class BatchQuantityChange
{
    public function __construct(
        public InventoryBatch $batch,
        public int $beforeQuantity,
        public int $afterQuantity,
    ) {
        if ($beforeQuantity < 0 || $afterQuantity < 0) {
            throw new InvalidArgumentException('Batch quantities cannot be negative.');
        }
    }

    public function changed(): bool
    {
        return $this->beforeQuantity !== $this->afterQuantity;
    }

    public function delta(): int
    {
        return $this->afterQuantity - $this->beforeQuantity;
    }
}
