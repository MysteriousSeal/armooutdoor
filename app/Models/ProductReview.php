<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'user_id',
    'order_id',
    'author_name',
    'source',
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

    /** A review typed in by hand from a marketplace, not posted by a customer. */
    public function isManual(): bool
    {
        return $this->user_id === null;
    }

    /**
     * First name and last-initial only — reviews are public, full names aren't.
     * A manual review carries its name as the marketplace already showed it.
     */
    public function reviewerName(): string
    {
        if ($this->user === null) {
            return (string) $this->author_name;
        }

        $lastInitial = mb_substr(trim($this->user->last_name ?? ''), 0, 1);

        return trim($this->user->first_name.($lastInitial !== '' ? ' '.$lastInitial.'.' : ''));
    }
}
