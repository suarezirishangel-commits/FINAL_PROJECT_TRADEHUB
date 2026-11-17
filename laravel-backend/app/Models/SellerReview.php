<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerReview extends Model
{
    protected $table = 'seller_reviews';

    protected $fillable = [
        'seller_id',
        'reviewer_id',
        'reference',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'reviewer_id' => 'integer',
            'rating' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
