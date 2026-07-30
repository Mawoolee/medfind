<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_name',
        'pharmacyAddress',
        'latitude',
        'longitude',
        'contactNumber',
        'user_id',
        'status',
    ];

    /**
     * Get the user that owns the pharmacy.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the inventory items for the pharmacy.
     */
    public function inventory()
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * Get the messages for the pharmacy.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}