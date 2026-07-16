<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    protected $fillable = [
        'name',
        'generic_name',
        'dosage_form',
        'category',
        'description',
        'requires_prescription',
    ];

    protected $casts = [
        'requires_prescription' => 'boolean',
    ];

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
