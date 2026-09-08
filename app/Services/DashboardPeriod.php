<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * La tranche de temps que tout le tableau de bord regarde.
 *
 * Une seule période porte la page entière : chaque chiffre, chaque courbe,
 * chaque classement s'y rapporte. Elle sait aussi dire la tranche
 * précédente de même longueur, qui est ce à quoi les écarts se comparent —
 * « vs les 30 jours précédents », jamais un « ▲12 % » sans référent.
 */
class DashboardPeriod
{
    public const DEFAULT = '30d';

    /** @var array<string, string> */
    public const OPTIONS = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'mtd' => 'Month to date',
        'all' => 'All time',
    ];

    private function __construct(
        public readonly string $key,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly Carbon $previousStart,
        public readonly Carbon $previousEnd,
    ) {}

    public static function resolve(?string $key): self
    {
        $key = array_key_exists((string) $key, self::OPTIONS) ? (string) $key : self::DEFAULT;

        $end = now()->endOfDay();

        $start = match ($key) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            '90d' => now()->subDays(89)->startOfDay(),
            'mtd' => now()->startOfMonth(),
            // From the first sale, not from a round date: an invented
            // boundary would open every chart on months the shop never
            // lived. With no sale at all the window is today's, which is
            // empty too and says so.
            'all' => self::firstSaleDay(),
            default => now()->subDays(29)->startOfDay(),
        };

        // La tranche précédente a exactement la même longueur et se termine
        // juste avant celle-ci : comparer 30 jours à un mois calendaire
        // ferait varier l'écart avec la longueur des mois.
        //
        // "All time" has none: nothing precedes the first sale. Its
        // previous window is therefore empty end to end, which the deltas
        // already read as "no reference" — a dash rather than an invented
        // percentage.
        $lengthInDays = (int) $start->diffInDays($end->copy()->startOfDay()) + 1;

        return new self(
            key: $key,
            start: $start,
            end: $end,
            previousStart: $key === 'all' ? $start->copy() : $start->copy()->subDays($lengthInDays)->startOfDay(),
            previousEnd: $start->copy()->subSecond(),
        );
    }

    public function label(): string
    {
        return self::OPTIONS[$this->key];
    }

    /** Ce à quoi l'écart se compare, nommé plutôt que sous-entendu. */
    public function comparisonLabel(): string
    {
        return match ($this->key) {
            'today' => 'vs yesterday',
            '7d' => 'vs previous 7 days',
            '90d' => 'vs previous 90 days',
            'mtd' => 'vs same length last month',
            'all' => 'since the first sale',
            default => 'vs previous 30 days',
        };
    }

    /**
     * The day of the first sale, or today when there has not been one.
     * Test orders are left out, as everywhere else on the page: they never
     * happened.
     */
    private static function firstSaleDay(): Carbon
    {
        $first = Order::query()
            ->excludingTest()
            ->whereNotIn('status', ['refunded', 'draft'])
            ->min('created_at');

        return $first === null ? now()->startOfDay() : Carbon::parse($first)->startOfDay();
    }

    /**
     * The chart counts by month as soon as the window passes four months:
     * beyond that, a point per day is hair rather than a line, and its
     * table twin has a row for every one of them.
     */
    public function bucketsByMonth(): bool
    {
        return $this->lengthInDays() > 120;
    }

    /** How many day-buckets the chart draws for this period. */
    public function lengthInDays(): int
    {
        return (int) $this->start->diffInDays($this->end->copy()->startOfDay()) + 1;
    }
}
