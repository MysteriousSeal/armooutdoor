<?php

namespace App\Support;

/**
 * Build donut chart slice data for admin analytics (CSS conic-gradient).
 */
class DonutChart
{
    /** @var list<string> */
    private const COLORS = [
        '#8b7e74',
        '#5b8a72',
        '#6b7f9a',
        '#a67c52',
        '#8a6a8a',
        '#6a8a8a',
        '#9a6b6b',
        '#7a8a5a',
        '#5a7a9a',
        '#9a8a5a',
    ];

    /**
     * @param  array<string, int>  $counts  label => count
     * @return array{
     *     total: int,
     *     gradient: string,
     *     slices: list<array{label: string, count: int, percent: float, color: string}>
     * }
     */
    public static function fromCounts(array $counts, int $maxSlices = 6): array
    {
        $counts = array_filter($counts, fn (int $count) => $count > 0);

        if ($counts === []) {
            return [
                'total' => 0,
                'gradient' => 'conic-gradient(var(--border) 0% 100%)',
                'slices' => [],
            ];
        }

        arsort($counts, SORT_NUMERIC);

        if (count($counts) > $maxSlices) {
            $top = array_slice($counts, 0, $maxSlices - 1, true);
            $other = array_sum(array_slice($counts, $maxSlices - 1, null, true));
            $counts = $top;
            if ($other > 0) {
                $counts['Other'] = $other;
            }
        }

        $total = array_sum($counts);
        $slices = [];
        $stops = [];
        $cursor = 0.0;
        $colorIndex = 0;

        foreach ($counts as $label => $count) {
            $percent = $total > 0 ? ($count / $total) * 100 : 0;
            $color = self::COLORS[$colorIndex % count(self::COLORS)];
            $colorIndex++;

            $start = $cursor;
            $end = $cursor + $percent;
            // Avoid floating point gaps on the last slice.
            if ($colorIndex === count($counts)) {
                $end = 100;
            }

            $stops[] = sprintf('%s %.4f%% %.4f%%', $color, $start, $end);
            $slices[] = [
                'label' => (string) $label,
                'count' => (int) $count,
                'percent' => round($percent, 1),
                'color' => $color,
            ];
            $cursor = $end;
        }

        return [
            'total' => $total,
            'gradient' => 'conic-gradient('.implode(', ', $stops).')',
            'slices' => $slices,
        ];
    }
}
