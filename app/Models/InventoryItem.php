<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = [
        'pharmacy_id',
        'medicine_id',
        'dosage',
        'stock_quantity',
        'price',
        'status',
        'last_updated',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
        'price'        => 'decimal:2',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryItem $item) {
            if ($item->stock_quantity <= 0) {
                $item->status = 'out_of_stock';
            } elseif ($item->stock_quantity <= 10) {
                $item->status = 'low_stock';
            } else {
                $item->status = 'in_stock';
            }
            $item->last_updated = now();
        });
    }
}
