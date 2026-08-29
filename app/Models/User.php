<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'pharmacy_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isPharmacy()
    {
        return in_array($this->role, ['pharmacy', 'pharmacy_operator'], true);
    }

    public function isConsumer()
    {
        return $this->role === 'consumer';
    }

    public function createdInventoryBatches()
    {
        return $this->hasMany(InventoryBatch::class, 'created_by');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
