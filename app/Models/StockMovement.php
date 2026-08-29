<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'operation_id',
        'inventory_item_id',
        'inventory_batch_id',
        'type',
        'before_quantity',
        'after_quantity',
        'quantity_delta',
        'reason',
        'reference_type',
        'reference_id',
        'received_reference',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'before_quantity' => 'integer',
            'after_quantity' => 'integer',
            'quantity_delta' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Stock movements are immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Stock movements are immutable.');
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
