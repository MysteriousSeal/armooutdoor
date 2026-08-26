<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Les mois que la comptabilité couvre.
 *
 * La boutique compte à partir de janvier 2026 ; un mois s'ajoute de lui-même
 * le premier jour du mois suivant, sans qu'on ait rien à créer.
 */
class AccountingPeriods
{
    public const FIRST = '2026-01';

    /**
     * Du mois en cours jusqu'au premier, le plus récent d'abord.
     *
     * C'est le mois qui vient de se clore qu'on ouvre presque toujours : il
     * doit rester en haut plutôt que descendre d'un cran chaque mois.
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

    /** Le mois demandé, ou null s'il ne fait pas partie de la période. */
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

        // Ni avant le premier mois compté, ni dans un futur qui n'a rien
        // encaissé : les deux répondent 404 plutôt qu'une page vide de plus.
        if ($parsed->lessThan(self::firstMonth()) || $parsed->greaterThan(self::currentMonth())) {
            return null;
        }

        return $parsed;
    }

    /** Pour l'administration, qui est en anglais. */
    public static function label(CarbonImmutable $month): string
    {
        return $month->locale('en')->isoFormat('MMMM YYYY');
    }

    /**
     * Pour les documents comptables, qui sont en français.
     *
     * Le français écrit les mois en minuscule, mais un titre de document et
     * une case d'en-tête commencent par une capitale.
     */
    public static function labelFr(CarbonImmutable $month): string
    {
        return Str::ucfirst($month->locale('fr')->isoFormat('MMMM')).' '.$month->format('Y');
    }

    /** Une date en toutes lettres, mois capitalisé : « 1 Juillet 2026 ». */
    public static function dateFr(CarbonImmutable $date, bool $withYear = true): string
    {
        return $date->format('j').' '
            .Str::ucfirst($date->locale('fr')->isoFormat('MMMM'))
            .($withYear ? ' '.$date->format('Y') : '');
    }

    /**
     * Un mois clos est un mois qu'on peut arrêter.
     *
     * Le mois en cours encaisse encore : un journal édité le 12 dirait
     * autre chose que le même journal édité le 30, et les deux circuleraient.
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
