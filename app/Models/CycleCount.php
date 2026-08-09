<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CycleCount extends Model
{
    protected $fillable = [
        'pharmacy_id',
        'name',
        'notes',
        'scheduled_at',
        'completed_at',
        'conducted_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CycleCountItem::class);
    }

    public function getIsCompletedAttribute(): bool
    {
        return !empty($this->completed_at);
    }
}
