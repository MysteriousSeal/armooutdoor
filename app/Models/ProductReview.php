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

        // Upper-cased on the way out: a customer who typed their name in
        // lower case still signs « Jean M. », like the blog comments do.
        $lastInitial = mb_strtoupper(mb_substr(trim($this->user->last_name ?? ''), 0, 1));

        return trim($this->user->first_name.($lastInitial !== '' ? ' '.$lastInitial.'.' : ''));
    }

    /**
     * The reviewer's monogram: the first letter of each of the first two
     * words of the name they sign with, so « Colas D. » wears CD. A one-word
     * name, such as a marketplace username, wears its first two letters
     * instead, so « lynxronin » wears LY rather than a lone L.
     */
    public function reviewerInitials(): string
    {
        $words = preg_split('/\s+/u', trim($this->reviewerName()), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_strtoupper(collect($words)
            ->take(2)
            ->map(fn (string $word): string => mb_substr($word, 0, 1))
            ->implode(''));
    }
}
