<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class InventoryBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'legacy_source_inventory_item_id',
        'batch_number',
        'lot_number',
        'identity_key',
        'quantity_received',
        'current_quantity',
        'price',
        'supplier_id',
        'supplier_name',
        'expiry_date',
        'cold_chain',
        'received_date',
        'received_reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
            'current_quantity' => 'integer',
            'price' => 'decimal:2',
            'expiry_date' => 'date',
            'cold_chain' => 'boolean',
            'received_date' => 'date',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function legacySourceInventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'legacy_source_inventory_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopePositive(Builder $query): Builder
    {
        return $query->where('current_quantity', '>', 0);
    }

    public function scopeAvailable(Builder $query, mixed $asOf = null): Builder
    {
        $date = $asOf === null ? now()->toDateString() : Carbon::parse($asOf)->toDateString();

        return $query->positive()->where(function (Builder $query) use ($date): void {
            $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', $date);
        });
    }

    public function scopeExpired(Builder $query, mixed $asOf = null): Builder
    {
        $date = $asOf === null ? now()->toDateString() : Carbon::parse($asOf)->toDateString();

        return $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $date);
    }

    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_date')
            ->orderBy('id');
    }
}
