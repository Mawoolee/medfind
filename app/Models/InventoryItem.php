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
        'status'
    ];

    // Or use this to allow all fields:
    // protected $guarded = [];
}