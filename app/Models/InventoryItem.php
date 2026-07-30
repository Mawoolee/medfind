<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'medicine_id',
        'stockQuantity',
        'price',
        'status',
    ];

    /**
     * Get the pharmacy that owns the inventory item.
     */
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /**
     * Get the medicine that owns the inventory item.
     */
    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}