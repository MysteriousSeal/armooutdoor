<?php

namespace Tests\Unit;

use App\Support\DonutChart;
use PHPUnit\Framework\TestCase;

class DonutChartTest extends TestCase
{
    public function test_builds_slices_sorted_by_count_with_percentages(): void
    {
        $chart = DonutChart::fromCounts(['A' => 1, 'B' => 3]);

        $this->assertSame(4, $chart['total']);
        $this->assertSame('B', $chart['slices'][0]['label']);
        $this->assertSame(75.0, $chart['slices'][0]['percent']);
        $this->assertStringStartsWith('conic-gradient(', $chart['gradient']);
    }

    public function test_collapses_overflow_into_other(): void
    {
        $counts = ['A' => 10, 'B' => 9, 'C' => 8, 'D' => 7, 'E' => 6, 'F' => 5, 'G' => 4];

        $chart = DonutChart::fromCounts($counts, 3);

        $labels = array_column($chart['slices'], 'label');
        $this->assertSame(['A', 'B', 'Other'], $labels);
        $this->assertSame(49, $chart['total']);
        $this->assertSame(30, $chart['slices'][2]['count']);
    }

    public function test_zero_counts_are_dropped_and_empty_input_is_safe(): void
    {
        $this->assertSame([], DonutChart::fromCounts(['A' => 0])['slices']);
        $this->assertSame(0, DonutChart::fromCounts([])['total']);
    }

    public function test_last_slice_closes_the_gradient_at_100_percent(): void
    {
        $chart = DonutChart::fromCounts(['A' => 1, 'B' => 1, 'C' => 1]);

        $this->assertStringContainsString('100.0000%)', $chart['gradient']);
    }
}
