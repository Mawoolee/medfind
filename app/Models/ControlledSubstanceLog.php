<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlledSubstanceLog extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'user_id',
        'action',      // received, dispensed, moved, audited
        'quantity',
        'notes',
        'logged_at',
        'operation_id',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
