<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeListing extends Model
{
    protected $table = 'trade_listings';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'wanted_item',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(TradeOffer::class, 'listing_id');
    }
}
