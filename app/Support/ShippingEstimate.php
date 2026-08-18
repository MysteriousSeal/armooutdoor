<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ShippingEstimate
{
    /**
     * Orders placed before 10am (Paris time) ship the same day, otherwise
     * the next day — pushed past the weekend when it lands on one.
     */
    public static function standard(Carbon $from): Carbon
    {
        $placedAt = $from->copy()->timezone('Europe/Paris');

        $base = $placedAt->hour < 10
            ? $placedAt->copy()->startOfDay()
            : $placedAt->copy()->addDay()->startOfDay();

        return self::nextBusinessDay($base);
    }

    /**
     * A backordered item's own estimate: the reference date plus the
     * supplier's lead time, also pushed past the weekend.
     */
    public static function backordered(Carbon $from, int $leadTimeDays): Carbon
    {
        return self::nextBusinessDay(
            $from->copy()->timezone('Europe/Paris')->addDays($leadTimeDays)->startOfDay()
        );
    }

    public static function nextBusinessDay(Carbon $date): Carbon
    {
        return match ($date->dayOfWeekIso) {
            6 => $date->addDays(2),
            7 => $date->addDays(1),
            default => $date,
        };
    }
}
