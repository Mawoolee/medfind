<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pharmacy extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'contact_number',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
