<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeOffer extends Model
{
    protected $table = 'trade_offers';

    protected $fillable = [
        'listing_id',
        'requester_id',
        'offered_item',
        'message',
        'status',
        'owner_confirmed_at',
        'requester_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'listing_id' => 'integer',
            'requester_id' => 'integer',
            'owner_confirmed_at' => 'datetime',
            'requester_confirmed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(TradeListing::class, 'listing_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
