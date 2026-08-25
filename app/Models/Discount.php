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

    /**
     * Admin-facing status. Separate from isActive() so a future window
     * can be labelled Scheduled instead of just "not active".
     */
    public function status(): string
    {
        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return $this->isActive() ? 'active' : 'expired';
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            'scheduled' => 'Scheduled',
            'active' => 'Active',
            default => 'Expired',
        };
    }

    public function typeLabel(): string
    {
        return $this->type === 'percentage' ? 'Percentage off' : 'Fixed amount off';
    }

    public function formattedStartsAt(): ?string
    {
        return $this->starts_at?->format('d M Y · H:i');
    }

    public function formattedEndsAt(): ?string
    {
        return $this->ends_at?->format('d M Y · H:i');
    }

    /**
     * English relative time for the admin list. The shop locale is French,
     * so the date goes through admin_relative_date(), which forces English.
     */
    public function remainingLabel(): string
    {
        $relative = fn ($date): string => admin_relative_date($date);

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'Starts '.$relative($this->starts_at);
        }

        if ($this->isActive()) {
            return $this->ends_at ? 'Ends '.$relative($this->ends_at) : 'No end date';
        }

        if ($this->ends_at !== null) {
            return 'Ended '.$relative($this->ends_at);
        }

        return '—';
    }
}
