<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = ['role_id', 'full_name', 'email', 'phone', 'address', 'password_hash', 'status'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function tradeListings(): HasMany
    {
        return $this->hasMany(TradeListing::class);
    }

    public function tradeOffers(): HasMany
    {
        return $this->hasMany(TradeOffer::class, 'requester_id');
    }

    public function isAdministrator(): bool
    {
        return $this->role?->name === 'Administrator';
    }

    public function isDeliveryRider(): bool
    {
        return $this->role?->name === 'Delivery Rider';
    }
}
