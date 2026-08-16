<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'type',
    'value',
    'starts_at',
    'ends_at',
])]
class Discount extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Whether this discount should currently be applied — unset dates
     * leave that side of the window open.
     */
    public function isActive(): bool
    {
        $now = now();

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * `value` is a percentage (1-100) for the `percentage` type, or cents
     * for the `fixed` type — same unit as every other *_cents column.
     */
    public function apply(int $priceCents): int
    {
        $discounted = $this->type === 'percentage'
            ? $priceCents - (int) round($priceCents * $this->value / 100)
            : $priceCents - $this->value;

        return max(0, $discounted);
    }

    public function label(): string
    {
        return $this->type === 'percentage'
            ? '-'.$this->value.'%'
            : '-'.format_euros($this->value);
    }
}
