<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'medicine_id',
        'stockQuantity',
        'price',
        'status',
        'expiry_date',
        'batch_number',
        'cold_chain',
        'par_level',
        'supplier_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'cold_chain' => 'boolean',
    ];

    /**
     * Get the pharmacy that owns the inventory item.
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /**
     * Get the medicine that owns the inventory item.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the supplier for this inventory item.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Inventory audit log entries for this item.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(InventoryAudit::class);
    }

    /**
     * Controlled substance log entries for this item.
     */
    public function controlledLogs(): HasMany
    {
        return $this->hasMany(ControlledSubstanceLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes / Queries
    |--------------------------------------------------------------------------
    */

    /**
     * FEFO (First-Expire, First-Out): items with an expiry date, nearest first.
     */
    public function scopeFefo($query)
    {
        return $query->whereNotNull('expiry_date')
            ->orderBy('expiry_date', 'asc');
    }

    /**
     * Items that have expired or will expire within the given number of days.
     */
    public function scopeExpiringWithin($query, $days = 90)
    {
        return $query->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()]);
    }

    /**
     * Items that have already expired.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay());
    }

    /**
     * Under-stocked items: stock at or below the par level.
     */
    public function scopeBelowPar($query)
    {
        return $query->whereColumn('stockQuantity', '<=', 'par_level')
            ->where('par_level', '>', 0);
    }

    /**
     * Items with zero stock.
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('stockQuantity', '<=', 0);
    }

    /**
     * Cold-chain (temperature-sensitive) items.
     */
    public function scopeColdChain($query)
    {
        return $query->where('cold_chain', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Classification Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Segregation category based on the medicine's prescription/category flags.
     * Returns one of: otc, prescription, controlled.
     */
    public function getSegregationAttribute(): string
    {
        $category = strtolower((string) optional($this->medicine)->category);
        $requiresRx = (bool) optional($this->medicine)->requiresPrescription;

        // Controlled substances override everything.
        if (in_array($category, ['controlled', 'narcotic', 's2', 's3', 'controlled substance'])) {
            return 'controlled';
        }

        if ($requiresRx) {
            return 'prescription';
        }

        return 'otc';
    }

    /**
     * Is this a controlled substance?
     */
    public function getIsControlledAttribute(): bool
    {
        return $this->segregation === 'controlled';
    }

    /**
     * Days remaining until expiry (null if no expiry, negative if expired).
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return now()->startOfDay()->diffInDays($this->expiry_date->startOfDay());
    }

    /**
     * Human-friendly expiry status label.
     */
    public function getExpiryStatusAttribute(): string
    {
        if (!$this->expiry_date) {
            return 'no_expiry';
        }
        $days = $this->days_until_expiry;
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'critical';
        }
        if ($days <= 90) {
            return 'short_dated';
        }
        return 'ok';
    }

    /**
     * Low-stock status label based on par level.
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stockQuantity <= 0) {
            return 'out_of_stock';
        }
        if ($this->par_level > 0 && $this->stockQuantity <= $this->par_level) {
            return 'low';
        }
        return 'ok';
    }

    /**
     * Compute the ABC (value-based) class for an inventory item.
     * A = high annual usage value, B = medium, C = low.
     */
    public function getAbcClassAttribute(): string
    {
        // Use stock value (stock * price) as a proxy for annual usage value.
        $value = (float) $this->stockQuantity * (float) $this->price;
        if ($value >= 100000) {
            return 'A';
        }
        if ($value >= 10000) {
            return 'B';
        }
        return 'C';
    }

    /**
     * Compute the VED (criticality) class for an inventory item.
     * Uses the medicine category to determine Vital / Essential / Desirable.
     */
    public function getVedClassAttribute(): string
    {
        $category = strtolower((string) optional($this->medicine)->category);
        $vital = ['antibiotic', 'antidiarrheal', 'antihistamine', 'insulin', 'anti-infective', 'cardiac', 'corticosteroid', 'vital'];
        $essential = ['analgesic', 'nsaid', 'antipyretic', 'essential', 'anti-inflammatory', 'antifungal', 'antimalarial'];
        $desirable = ['vitamin', 'supplement', 'desirable', 'cold', 'cough', 'antacid', 'laxative', 'otc'];

        if (in_array($category, $vital)) {
            return 'V';
        }
        if (in_array($category, $essential)) {
            return 'E';
        }
        if (in_array($category, $desirable)) {
            return 'D';
        }

        // Fallback: prescription meds are Vital, OTC over-the-counter are Desirable.
        if ((bool) optional($this->medicine)->requiresPrescription) {
            return 'V';
        }
        return 'D';
    }

    /**
     * Combined ABC-VED matrix class.
     */
    public function getAbcVedClassAttribute(): string
    {
        $abc = $this->abc_class;
        $ved = $this->ved_class;
        $map = [
            'A' => ['V' => 'I', 'E' => 'I', 'D' => 'II'],
            'B' => ['V' => 'I', 'E' => 'II', 'D' => 'III'],
            'C' => ['V' => 'II', 'E' => 'III', 'D' => 'III'],
        ];
        return $map[$abc][$ved] ?? 'III';
    }

    /**
     * Record an audit entry for a quantity change. Call before/after saving.
     */
    public function recordAudit(int $before, int $after, ?string $notes = null): void
    {
        if ($before === $after) {
            return;
        }
        InventoryAudit::create([
            'inventory_item_id' => $this->id,
            'user_id' => auth()->id(),
            'before_quantity' => $before,
            'after_quantity' => $after,
            'notes' => $notes,
        ]);
    }
}
