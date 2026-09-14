<?php

namespace Tests\Unit;

use App\Models\Discount;
use Tests\TestCase;

/**
 * A euro discount read as a share of the price it comes off, the way the
 * product cards and the homepage sale slide state it.
 */
class DiscountPercentageTest extends TestCase
{
    private function discount(string $type, int $value): Discount
    {
        return new Discount(['type' => $type, 'value' => $value]);
    }

    public function test_a_percentage_discount_keeps_its_own_figure(): void
    {
        $this->assertSame(20, $this->discount('percentage', 20)->percentageOf(1690));
    }

    public function test_a_fixed_amount_becomes_a_share_of_the_price(): void
    {
        // 10 € off 40 €.
        $this->assertSame(25, $this->discount('fixed', 1000)->percentageOf(4000));
    }

    public function test_the_share_is_rounded_down(): void
    {
        // 2 € off 16,90 € is 11.83%: rounding up would promise more than it gives.
        $this->assertSame(11, $this->discount('fixed', 200)->percentageOf(1690));
    }

    public function test_an_amount_above_the_price_stops_at_a_hundred(): void
    {
        $this->assertSame(100, $this->discount('fixed', 5000)->percentageOf(3000));
    }

    public function test_under_one_percent_or_without_a_price_there_is_no_figure(): void
    {
        $this->assertNull($this->discount('fixed', 1)->percentageOf(30000));
        $this->assertNull($this->discount('fixed', 200)->percentageOf(0));
    }

    public function test_the_label_reads_as_a_percentage(): void
    {
        $this->assertSame('-11%', $this->discount('fixed', 200)->percentageLabel(1690));
        $this->assertSame('-20%', $this->discount('percentage', 20)->percentageLabel(1690));
    }

    public function test_the_label_keeps_the_amount_when_no_percentage_can_be_given(): void
    {
        // "-0%" would say there is no discount at all.
        $this->assertSame('-'.format_euros(1), $this->discount('fixed', 1)->percentageLabel(30000));
    }
}
