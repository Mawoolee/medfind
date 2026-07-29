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
        'status', // Add this
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inventory()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}