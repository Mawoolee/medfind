<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_name',
        'dosage',
        'manufacturer',
        'requiresPrescription',
        'category',
    ];

    /**
     * Get the inventory items for the medicine.
     */
    public function inventory()
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * Check if medicine requires prescription.
     */
    public function getRequiresPrescriptionAttribute($value)
    {
        return (bool) $value;
    }
}