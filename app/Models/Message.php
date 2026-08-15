<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Pharmacy;

class Message extends Model
{
    use HasFactory;
    protected $fillable = [
        'consumer_id',
        'pharmacy_id',
        'message',
        'prescription_image',
        'attachments',
        'reply',
        'replied_at',
        'is_read',
        // verification fields
        'verified_by',
        'verification_status',
        'verification_notes',
        'verified_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'replied_at' => 'datetime',
        'verified_at' => 'datetime',
        'attachments' => 'array',
    ];

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumer_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
