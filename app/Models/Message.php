<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'consumer_id',
        'pharmacy_id',
        'content',
        'sender',
        'is_read',
        'prescription_file',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumer_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
