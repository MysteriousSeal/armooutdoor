<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code',
    'type',
    'value',
    'user_id',
    'quantity',
])]
class DiscountCode extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isForCustomer(): bool
    {
        return $this->user_id !== null;
    }

    public function hasLimitedQuantity(): bool
    {
        return $this->quantity !== null;
    }

    /**
     * Applies to the whole cart total, unlike a product Discount.
     * `value` is a percentage (1-100) for `percentage`, or cents for `fixed`.
     */
    public function apply(int $totalCents): int
    {
        $discounted = $this->type === 'percentage'
            ? $totalCents - (int) round($totalCents * $this->value / 100)
            : $totalCents - $this->value;

        return max(0, $discounted);
    }

    public function label(): string
    {
        return $this->type === 'percentage'
            ? '-'.$this->value.'%'
            : '-'.format_euros($this->value);
    }

    public function quantityLabel(): string
    {
        return $this->hasLimitedQuantity() ? (string) $this->quantity : 'Unlimited';
    }
}
