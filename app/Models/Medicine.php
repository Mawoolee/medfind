<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_name',
        'brand_name',
        'dosage',
        'manufacturer',
        'requiresPrescription',
        'cold_chain_required',
        'category',
    ];

    protected $casts = [
        'requiresPrescription' => 'boolean',
        'cold_chain_required' => 'boolean',
    ];

    /**
     * Get the inventory items for the medicine.
     */
    public function inventory()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function inventoryBatches()
    {
        return $this->hasManyThrough(InventoryBatch::class, InventoryItem::class);
    }

    /**
     * Check if medicine requires prescription.
     */
    public function getRequiresPrescriptionAttribute($value)
    {
        return (bool) $value;
    }
}
