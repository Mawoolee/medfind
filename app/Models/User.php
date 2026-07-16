<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPharmacyOperator(): bool
    {
        return $this->role === 'pharmacy_operator';
    }

    public function isConsumer(): bool
    {
        return $this->role === 'consumer';
    }

    public function pharmacy(): HasOne
    {
        return $this->hasOne(Pharmacy::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'consumer_id');
    }
}
