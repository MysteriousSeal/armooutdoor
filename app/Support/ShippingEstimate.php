<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Carbon;

class ShippingEstimate
{
    /**
     * Where the shop's closures come from: pairs of Y-m-d days, both
     * included. Set at boot from the holidays table; left unset (plain
     * unit tests), only weekends are skipped.
     *
     * @var (Closure(): iterable<array{0: string, 1: string}>)|null
     */
    private static ?Closure $closures = null;

    public static function closuresUsing(?Closure $resolver): void
    {
        self::$closures = $resolver;
    }

    /**
     * Orders placed before 10am (Paris time) ship the same day, otherwise
     * the next day, pushed past the weekend or a closure when it lands on one.
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
     * supplier's lead time, also pushed past weekends and closures.
     */
    public static function backordered(Carbon $from, int $leadTimeDays): Carbon
    {
        return self::nextBusinessDay(
            $from->copy()->timezone('Europe/Paris')->addDays($leadTimeDays)->startOfDay()
        );
    }

    public static function nextBusinessDay(Carbon $date): Carbon
    {
        $closures = self::$closures ? (self::$closures)() : [];

        // A closure can end on a Friday and a weekend can run into a
        // closure, so step until the day is clear of both.
        while (true) {
            if ($date->isWeekend()) {
                $date->addDay();

                continue;
            }

            $day = $date->toDateString();

            foreach ($closures as [$from, $to]) {
                if ($day >= $from && $day <= $to) {
                    $date->setDateFrom(Carbon::parse($to, $date->getTimezone()))->addDay();

                    continue 2;
                }
            }

            return $date;
        }
    }
}
