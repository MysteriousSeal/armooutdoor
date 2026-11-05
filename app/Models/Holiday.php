<?php

namespace App\Models;

use App\Support\ShippingEstimate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Once;

/**
 * A closure of the shop: no parcel leaves from its first day to its last,
 * both included. Ship-date estimates step over it, and the storefront
 * announces it ahead of time.
 */
#[Fillable(['starts_on', 'ends_on'])]
class Holiday extends Model
{
    /** How many days ahead the storefront starts announcing a closure. */
    public const ANNOUNCE_DAYS = 14;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // periods() and announced() are memoized for the request; a change
        // must not leave the old list answering.
        static::saved(fn () => Once::flush());
        static::deleted(fn () => Once::flush());
    }

    /**
     * Every closure as a pair of Y-m-d strings, soonest first. Past ones
     * stay in: an old order's ship date is worked out again on each view.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function periods(): array
    {
        return once(fn (): array => static::query()
            ->orderBy('starts_on')
            ->get()
            ->map(fn (self $holiday): array => [$holiday->starts_on->toDateString(), $holiday->ends_on->toDateString()])
            ->all());
    }

    /** The closure under way or starting within ANNOUNCE_DAYS, if any. */
    public static function announced(): ?self
    {
        return once(function (): ?self {
            $today = now('Europe/Paris')->startOfDay();

            // whereDate, not where: SQLite keeps a date cast as "Y-m-d 00:00:00",
            // which sorts after the bare day and would miss the boundary day.
            return static::query()
                ->whereDate('ends_on', '>=', $today->toDateString())
                ->whereDate('starts_on', '<=', $today->copy()->addDays(self::ANNOUNCE_DAYS)->toDateString())
                ->orderBy('starts_on')
                ->first();
        });
    }

    /** The first day parcels leave again, past the weekend and any closure that follows. */
    public function resumesOn(): Carbon
    {
        return ShippingEstimate::nextBusinessDay(
            Carbon::parse($this->ends_on->toDateString(), 'Europe/Paris')->addDay()
        );
    }
}
