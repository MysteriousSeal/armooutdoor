<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The months the accounts cover.
 *
 * The shop starts counting in January 2026, and a month adds itself on the
 * first of the next one, with nothing to create.
 */
class AccountingPeriods
{
    public const FIRST = '2026-01';

    /**
     * From the current month back to the first, newest first.
     *
     * The month that has just closed is the one opened almost every time, so
     * it stays at the top rather than dropping a row each month.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public static function months(): Collection
    {
        $first = self::firstMonth();
        $months = collect();

        for ($month = self::currentMonth(); $month->greaterThanOrEqualTo($first); $month = $month->subMonth()) {
            $months->push($month);
        }

        return $months;
    }

    /** The month asked for, or null when it falls outside the period. */
    public static function parse(?string $month): ?CarbonImmutable
    {
        if (! is_string($month) || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01');

        if ($parsed === false) {
            return null;
        }

        $parsed = $parsed->startOfMonth();

        // Neither before the first month counted, nor in a future that has
        // taken nothing in: both answer 404 rather than one more empty page.
        if ($parsed->lessThan(self::firstMonth()) || $parsed->greaterThan(self::currentMonth())) {
            return null;
        }

        return $parsed;
    }

    /** For the admin, which is in English. */
    public static function label(CarbonImmutable $month): string
    {
        return $month->locale('en')->isoFormat('MMMM YYYY');
    }

    /**
     * For the accounting documents, which are in French.
     *
     * French writes months in lowercase, but a document title and a header
     * box start with a capital.
     */
    public static function labelFr(CarbonImmutable $month): string
    {
        return Str::ucfirst($month->locale('fr')->isoFormat('MMMM')).' '.$month->format('Y');
    }

    /** A date written out, month capitalised: "1 Juillet 2026". */
    public static function dateFr(CarbonImmutable $date, bool $withYear = true): string
    {
        return $date->format('j').' '
            .Str::ucfirst($date->locale('fr')->isoFormat('MMMM'))
            .($withYear ? ' '.$date->format('Y') : '');
    }

    /**
     * A closed month is one that can be ruled off.
     *
     * The current month is still taking money in: a journal printed on the
     * 12th would not say what the same journal says on the 30th, and both
     * would be filed.
     */
    public static function isClosed(CarbonImmutable $month): bool
    {
        return $month->lessThan(self::currentMonth());
    }

    public static function key(CarbonImmutable $month): string
    {
        return $month->format('Y-m');
    }

    private static function firstMonth(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', self::FIRST.'-01')->startOfMonth();
    }

    private static function currentMonth(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMonth();
    }
}
