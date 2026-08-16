<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'user_id',
    'order_id',
    'rating',
    'comment',
])]
class ProductReview extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * First name and last-initial only — reviews are public, full names aren't.
     */
    public function reviewerName(): string
    {
        $lastInitial = mb_substr(trim($this->user->last_name ?? ''), 0, 1);

        return trim($this->user->first_name.($lastInitial !== '' ? ' '.$lastInitial.'.' : ''));
    }
}
