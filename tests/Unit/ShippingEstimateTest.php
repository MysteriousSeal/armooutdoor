<?php

namespace Tests\Unit;

use App\Support\ShippingEstimate;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ShippingEstimateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A feature test run earlier in the process leaves the holidays
        // table as the closure source; here there is no app to read it.
        ShippingEstimate::closuresUsing(null);
    }

    public function test_a_closure_pushes_the_date_past_its_last_day_and_the_weekend(): void
    {
        ShippingEstimate::closuresUsing(fn (): array => [['2026-08-19', '2026-08-21']]);

        $result = ShippingEstimate::nextBusinessDay(Carbon::parse('2026-08-19', 'Europe/Paris')); // Wednesday

        $this->assertTrue($result->isSameDay(Carbon::parse('2026-08-24', 'Europe/Paris')));
    }

    public function test_an_order_placed_before_10am_paris_time_ships_the_same_day(): void
    {
        $placedAt = Carbon::parse('2026-08-19 08:00:00', 'Europe/Paris'); // Wednesday

        $result = ShippingEstimate::standard($placedAt);

        $this->assertTrue($result->isSameDay($placedAt));
    }

    public function test_an_order_placed_after_10am_paris_time_ships_the_next_day(): void
    {
        $placedAt = Carbon::parse('2026-08-19 14:00:00', 'Europe/Paris'); // Wednesday

        $result = ShippingEstimate::standard($placedAt);

        $this->assertTrue($result->isSameDay($placedAt->copy()->addDay()));
    }

    public function test_an_order_placed_right_at_10am_ships_the_next_day(): void
    {
        $placedAt = Carbon::parse('2026-08-19 10:00:00', 'Europe/Paris');

        $result = ShippingEstimate::standard($placedAt);

        $this->assertTrue($result->isSameDay($placedAt->copy()->addDay()));
    }

    public function test_a_friday_afternoon_order_ships_the_following_monday(): void
    {
        $placedAt = Carbon::parse('2026-08-21 15:00:00', 'Europe/Paris'); // Friday

        $result = ShippingEstimate::standard($placedAt);

        $this->assertSame(1, $result->dayOfWeekIso); // Monday
        $this->assertTrue($result->isSameDay(Carbon::parse('2026-08-24', 'Europe/Paris')));
    }

    public function test_a_saturday_morning_order_ships_the_following_monday(): void
    {
        $placedAt = Carbon::parse('2026-08-22 08:00:00', 'Europe/Paris'); // Saturday

        $result = ShippingEstimate::standard($placedAt);

        $this->assertSame(1, $result->dayOfWeekIso);
    }

    public function test_next_business_day_pushes_saturday_to_monday(): void
    {
        $saturday = Carbon::parse('2026-08-22', 'Europe/Paris');

        $this->assertSame(1, ShippingEstimate::nextBusinessDay($saturday)->dayOfWeekIso);
    }

    public function test_next_business_day_pushes_sunday_to_monday(): void
    {
        $sunday = Carbon::parse('2026-08-23', 'Europe/Paris');

        $this->assertSame(1, ShippingEstimate::nextBusinessDay($sunday)->dayOfWeekIso);
    }

    public function test_next_business_day_leaves_a_weekday_untouched(): void
    {
        $wednesday = Carbon::parse('2026-08-19', 'Europe/Paris');

        $this->assertTrue(ShippingEstimate::nextBusinessDay($wednesday)->isSameDay($wednesday));
    }

    public function test_backordered_adds_the_suppliers_lead_time(): void
    {
        $from = Carbon::parse('2026-08-19 08:00:00', 'Europe/Paris'); // Wednesday

        $result = ShippingEstimate::backordered($from, 2);

        $this->assertTrue($result->isSameDay(Carbon::parse('2026-08-21', 'Europe/Paris'))); // Friday, a business day
    }

    public function test_backordered_lead_time_landing_on_a_weekend_is_pushed_to_monday(): void
    {
        $from = Carbon::parse('2026-08-19 08:00:00', 'Europe/Paris'); // Wednesday + 3 days = Saturday

        $result = ShippingEstimate::backordered($from, 3);

        $this->assertSame(1, $result->dayOfWeekIso);
    }

    public function test_backordered_with_zero_lead_time_still_pushes_past_a_weekend(): void
    {
        $from = Carbon::parse('2026-08-22 08:00:00', 'Europe/Paris'); // Saturday

        $result = ShippingEstimate::backordered($from, 0);

        $this->assertSame(1, $result->dayOfWeekIso);
    }
}
