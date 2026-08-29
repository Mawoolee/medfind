<?php

namespace App\Domain\Inventory\Data;

use App\Models\InventoryAudit;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

final readonly class StockOperationResult
{
    /**
     * @param  Collection<int, StockMovement>  $movements
     */
    public function __construct(
        public string $operationId,
        public InventoryItem $aggregate,
        public Collection $movements,
        public ?InventoryAudit $audit = null,
    ) {}
}
