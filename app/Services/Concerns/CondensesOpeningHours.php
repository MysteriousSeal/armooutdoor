<?php

namespace App\Services\Concerns;

trait CondensesOpeningHours
{
    /**
     * Groups consecutive days sharing the same opening hours into a single
     * "Lun-Ven HH:MM-HH:MM" line instead of repeating it once per day.
     * Expects one entry per day, in Monday-to-Sunday order.
     *
     * @param  array<int, array{label: string, range: ?string}>  $days
     */
    private function condenseDays(array $days): ?string
    {
        $labels = array_column($days, 'label');
        $groups = [];

        foreach ($days as $index => $day) {
            if ($day['range'] === null) {
                continue;
            }

            $previousLabel = $index > 0 ? $labels[$index - 1] : null;
            $lastKey = $groups === [] ? null : array_key_last($groups);
            $last = $lastKey === null ? null : $groups[$lastKey];

            if ($last !== null && $last['range'] === $day['range'] && $last['lastLabel'] === $previousLabel) {
                $groups[$lastKey]['end'] = $day['label'];
                $groups[$lastKey]['lastLabel'] = $day['label'];

                continue;
            }

            $groups[] = [
                'start' => $day['label'],
                'end' => $day['label'],
                'lastLabel' => $day['label'],
                'range' => $day['range'],
            ];
        }

        if ($groups === []) {
            return null;
        }

        return implode(' · ', array_map(
            fn (array $g): string => ($g['start'] === $g['end'] ? $g['start'] : $g['start'].'-'.$g['end']).' '.$g['range'],
            $groups,
        ));
    }
}
