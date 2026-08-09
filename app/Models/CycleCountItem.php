<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCountItem extends Model
{
    protected $fillable = [
        'cycle_count_id',
        'inventory_item_id',
        'expected_quantity',
        'counted_quantity',
        'notes',
    ];

    public function cycleCount(): BelongsTo
    {
        return $this->belongsTo(CycleCount::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function getDiscrepancyAttribute(): int
    {
        return (int) $this->counted_quantity - (int) $this->expected_quantity;
    }
}
