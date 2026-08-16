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
    'max_uses_per_customer',
    'ends_at',
])]
class DiscountCode extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'quantity' => 'integer',
            'max_uses_per_customer' => 'integer',
            'ends_at' => 'datetime',
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

    public function typeLabel(): string
    {
        return $this->type === 'percentage' ? 'Percentage off' : 'Fixed amount off';
    }

    public function quantityLabel(): string
    {
        return $this->hasLimitedQuantity() ? (string) $this->quantity : 'Unlimited';
    }

    public function hasMaxUsesPerCustomer(): bool
    {
        return $this->max_uses_per_customer !== null;
    }

    public function maxUsesPerCustomerLabel(): string
    {
        return $this->hasMaxUsesPerCustomer() ? (string) $this->max_uses_per_customer : 'Unlimited';
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function formattedEndsAt(): ?string
    {
        return $this->ends_at?->format('d M Y · H:i');
    }

    /**
     * English relative time for the admin list. The shop locale is French,
     * so Carbon's default diffForHumans() would otherwise leak FR copy.
     */
    public function remainingLabel(): string
    {
        if ($this->ends_at === null) {
            return 'No deadline';
        }

        $relative = $this->ends_at->copy()->locale('en')->diffForHumans();

        return $this->isExpired() ? 'Expired '.$relative : 'Expires '.$relative;
    }
}
