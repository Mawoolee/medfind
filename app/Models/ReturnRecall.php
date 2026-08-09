<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRecall extends Model
{
    protected $table = 'returns_recalls';

    protected $fillable = [
        'inventory_item_id',
        'type',          // return or recall
        'quantity',
        'reason',
        'status',        // pending, approved, completed, rejected
        'requested_by',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
