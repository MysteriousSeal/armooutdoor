<?php

namespace App\Support;

/**
 * Reconstructs anonymous "sessions" from SiteVisit rows (no session id is
 * stored) and lays out an 8-step Sankey-style diagram of where visitors go:
 * entrance page group, then up to seven more distinct groups, with visitors
 * who stop at each step draining into a "Left the site" node.
 */
class SessionFlow
{
    public const STEPS = 8;

    public const SESSION_GAP_MINUTES = 30;

    public const EXIT_LABEL = 'Left the site';

    /** @var array<string, string> */
    private const COLORS = [
        'Home' => '#8b7e74',
        'Browse' => '#6b7f9a',
        'Product' => '#5b8a72',
        'Cart' => '#a67c52',
        'Checkout' => '#8a6a8a',
        'Order' => '#6a8a8a',
        'Account' => '#9a6b6b',
        'Blog' => '#7a8a5a',
        'Info' => '#5a7a9a',
        'Other' => '#9a8a5a',
    ];

    private const EXIT_COLOR = '#b7aea5';

    private const NODE_WIDTH = 18;

    private const NODE_GAP = 8;

    /**
     * The section of the site a path belongs to, coarse enough that a
     * flow diagram stays readable instead of one node per product.
     */
    public static function pageGroup(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return match (true) {
            $path === '/' => 'Home',
            str_starts_with($path, '/products/') => 'Product',
            str_starts_with($path, '/categories'),
            str_starts_with($path, '/search'),
            in_array($path, ['/nouveautes', '/promotions', '/meilleures-ventes'], true) => 'Browse',
            str_starts_with($path, '/blog') => 'Blog',
            str_starts_with($path, '/cart') => 'Cart',
            str_starts_with($path, '/checkout') => 'Checkout',
            str_starts_with($path, '/orders') => 'Order',
            str_starts_with($path, '/account'),
            str_starts_with($path, '/wishlist'),
            str_starts_with($path, '/reset-password'),
            in_array($path, ['/login', '/register', '/forgot-password', '/logout'], true) => 'Account',
            in_array($path, ['/cgv', '/mentions-legales', '/confidentialite', '/droit-de-retractation', '/faq', '/contact', '/livraison-et-retours', '/paiement-securise'], true),
            str_starts_with($path, '/messages') => 'Info',
            default => 'Other',
        };
    }

    /**
     * A session's raw ordered paths, collapsed to distinct consecutive page
     * groups and capped to the first STEPS of them.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function groupSequence(array $paths): array
    {
        $groups = [];

        foreach ($paths as $path) {
            $group = self::pageGroup($path);

            if ($groups === [] || end($groups) !== $group) {
                $groups[] = $group;
            }

            if (count($groups) >= self::STEPS) {
                break;
            }
        }

        return $groups;
    }

    /**
     * @param  list<list<string>>  $sessions  Each a groupSequence() result.
     * @return array{total: int, columns: list<array{title: string, x: float}>, nodes: list<array<string, mixed>>, links: list<array<string, mixed>>, table: list<array<string, mixed>>, legend: list<array{label: string, color: string}>}
     */
    public static function build(array $sessions, int $width = 1400, int $height = 420): array
    {
        $sessions = array_values(array_filter($sessions, fn (array $s) => $s !== []));
        $total = count($sessions);

        $columnTitles = array_map(
            fn (int $i) => $i === 0 ? 'Entrance' : 'Step '.($i + 1),
            range(0, self::STEPS - 1),
        );

        if ($total === 0) {
            return ['total' => 0, 'columns' => [], 'nodes' => [], 'links' => [], 'table' => [], 'legend' => []];
        }

        $nodeCounts = array_fill(0, self::STEPS, []);
        $linkCounts = array_fill(0, self::STEPS - 1, []);

        foreach ($sessions as $seq) {
            $len = count($seq);

            for ($step = 0; $step < min($len, self::STEPS); $step++) {
                $label = $seq[$step];
                $nodeCounts[$step][$label] = ($nodeCounts[$step][$label] ?? 0) + 1;
            }

            for ($layer = 0; $layer < self::STEPS - 1; $layer++) {
                if ($layer >= $len) {
                    break;
                }

                $from = $seq[$layer];
                $to = ($layer + 1) < $len ? $seq[$layer + 1] : self::EXIT_LABEL;

                if ($to === self::EXIT_LABEL) {
                    $nodeCounts[$layer + 1][self::EXIT_LABEL] = ($nodeCounts[$layer + 1][self::EXIT_LABEL] ?? 0) + 1;
                }

                $linkKey = $from.'|'.$to;
                $linkCounts[$layer][$linkKey] = ($linkCounts[$layer][$linkKey] ?? [
                    'from' => $from,
                    'to' => $to,
                    'count' => 0,
                ]);
                $linkCounts[$layer][$linkKey]['count']++;
            }
        }

        $availableHeight = $height - (2 * self::NODE_GAP);
        $entranceNodeCount = max(1, count($nodeCounts[0]));
        $gapBudget = ($entranceNodeCount - 1) * self::NODE_GAP;
        $scale = ($availableHeight - $gapBudget) / $total;

        $colGap = self::STEPS > 1 ? ($width - self::NODE_WIDTH) / (self::STEPS - 1) : 0;

        $nodeInfo = [];
        $nodes = [];

        for ($step = 0; $step < self::STEPS; $step++) {
            $labels = self::orderColumnLabels($nodeCounts[$step]);
            $x = $step * $colGap;
            $y = self::NODE_GAP;

            foreach ($labels as $label) {
                $count = $nodeCounts[$step][$label];
                $nodeHeight = max(2, $count * $scale);

                $nodeInfo[$step][$label] = ['y0' => $y, 'y1' => $y + $nodeHeight, 'out' => $y, 'in' => $y];

                $nodes[] = [
                    'key' => $step.':'.$label,
                    'step' => $step,
                    'label' => $label,
                    'count' => $count,
                    'percent' => round(($count / $total) * 100, 1),
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                    'width' => self::NODE_WIDTH,
                    'height' => round($nodeHeight, 2),
                    'color' => self::colorFor($label),
                    'isExit' => $label === self::EXIT_LABEL,
                    // A label on a sliver of a node collides with its
                    // neighbours; the tooltip still carries the numbers.
                    'labelVisible' => $nodeHeight >= 12,
                ];

                $y += $nodeHeight + self::NODE_GAP;
            }
        }

        $links = [];
        $table = [];

        for ($layer = 0; $layer < self::STEPS - 1; $layer++) {
            $entries = array_values($linkCounts[$layer]);

            usort($entries, fn (array $a, array $b) => self::labelOrder($nodeCounts[$layer], $a['from']) <=> self::labelOrder($nodeCounts[$layer], $b['from'])
                ?: self::labelOrder($nodeCounts[$layer + 1], $a['to']) <=> self::labelOrder($nodeCounts[$layer + 1], $b['to']));

            $rightX = $layer * $colGap + self::NODE_WIDTH;
            $leftX = ($layer + 1) * $colGap;
            $midX = ($rightX + $leftX) / 2;

            foreach ($entries as $entry) {
                ['from' => $from, 'to' => $to, 'count' => $count] = $entry;
                $segHeight = $count * $scale;

                $sourceY0 = $nodeInfo[$layer][$from]['out'];
                $sourceY1 = $sourceY0 + $segHeight;
                $nodeInfo[$layer][$from]['out'] = $sourceY1;

                $targetY0 = $nodeInfo[$layer + 1][$to]['in'];
                $targetY1 = $targetY0 + $segHeight;
                $nodeInfo[$layer + 1][$to]['in'] = $targetY1;

                $links[] = [
                    'd' => sprintf(
                        'M%.2f,%.2f C%.2f,%.2f %.2f,%.2f %.2f,%.2f L%.2f,%.2f C%.2f,%.2f %.2f,%.2f %.2f,%.2f Z',
                        $rightX, $sourceY0, $midX, $sourceY0, $midX, $targetY0, $leftX, $targetY0,
                        $leftX, $targetY1, $midX, $targetY1, $midX, $sourceY1, $rightX, $sourceY1,
                    ),
                    'color' => self::colorFor($from),
                    'from' => $layer.':'.$from,
                    'to' => ($layer + 1).':'.$to,
                    'label' => $from.' → '.$to,
                    'count' => $count,
                    'percent' => round(($count / $total) * 100, 1),
                ];

                $table[] = [
                    'layer' => $layer + 1,
                    'from' => $from,
                    'to' => $to,
                    'count' => $count,
                    'percent' => round(($count / $total) * 100, 1),
                ];
            }
        }

        // One swatch per page group actually on the diagram, in reading
        // order (columns left to right, busiest first); the exit last.
        $legend = [];

        foreach ($nodes as $node) {
            if (! $node['isExit'] && ! isset($legend[$node['label']])) {
                $legend[$node['label']] = ['label' => $node['label'], 'color' => $node['color']];
            }
        }

        if (array_filter($nodes, fn (array $node) => $node['isExit']) !== []) {
            $legend[self::EXIT_LABEL] = ['label' => self::EXIT_LABEL, 'color' => self::EXIT_COLOR];
        }

        return [
            'total' => $total,
            'width' => $width,
            'height' => $height,
            'columns' => array_map(
                fn (string $title, int $i) => ['title' => $title, 'x' => round($i * $colGap, 2)],
                $columnTitles,
                array_keys($columnTitles),
            ),
            'nodes' => $nodes,
            'links' => $links,
            'table' => $table,
            'legend' => array_values($legend),
        ];
    }

    private static function colorFor(string $label): string
    {
        return self::COLORS[$label] ?? self::EXIT_COLOR;
    }

    /**
     * Real page groups first (busiest first), "Left the site" always last —
     * it reads as a drain at the bottom of the column, not just another stop.
     *
     * @param  array<string, int>  $counts
     * @return list<string>
     */
    private static function orderColumnLabels(array $counts): array
    {
        $exit = null;

        if (isset($counts[self::EXIT_LABEL])) {
            $exit = self::EXIT_LABEL;
            unset($counts[self::EXIT_LABEL]);
        }

        arsort($counts, SORT_NUMERIC);
        $labels = array_keys($counts);

        if ($exit !== null) {
            $labels[] = $exit;
        }

        return $labels;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private static function labelOrder(array $counts, string $label): int
    {
        return array_search($label, self::orderColumnLabels($counts), true) ?: 0;
    }
}
