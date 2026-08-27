<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AccountingPeriods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The list of accounting months, and a month's own page. */
class AccountingMonthsTest extends TestCase
{
    use RefreshDatabase;

    /** The rendered list of months for a section. */
    private function list(string $section = 'sales'): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/'.$section)
            ->assertOk()
            ->getContent();
    }

    public function test_the_list_runs_from_the_current_month_back_to_january_2026(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        $months = AccountingPeriods::months();

        $this->assertSame('2026-08', AccountingPeriods::key($months->first()));
        $this->assertSame('2026-01', AccountingPeriods::key($months->last()));
        $this->assertCount(8, $months);
    }

    public function test_the_newest_month_comes_first(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        $html = $this->list();

        // The month that has just closed stays at the top rather than
        // dropping a row each month. The name no longer ends its element —
        // a marker can follow it — so the match allows for that.
        $this->assertLessThan(
            preg_match('/accounting-month-name">\s*January/', $html, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : PHP_INT_MAX,
            preg_match('/accounting-month-name">\s*August/', $html, $a, PREG_OFFSET_CAPTURE) ? $a[0][1] : PHP_INT_MAX,
        );
    }

    public function test_a_new_month_appears_on_the_first(): void
    {
        $this->travelTo('2026-08-31 23:59:59');
        $this->assertCount(8, AccountingPeriods::months());

        $this->travelTo('2026-09-01 00:00:01');
        $months = AccountingPeriods::months();

        $this->assertCount(9, $months);
        $this->assertSame('2026-09', AccountingPeriods::key($months->first()));
    }

    public function test_the_years_are_kept_apart(): void
    {
        $this->travelTo('2027-02-10 10:00:00');

        $html = $this->list();

        $this->assertStringContainsString('accounting-year-2027', $html);
        $this->assertStringContainsString('accounting-year-2026', $html);
        $this->assertCount(14, AccountingPeriods::months());
    }

    public function test_the_month_in_progress_is_marked(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        $html = $this->list();

        $this->assertStringContainsString('is-current', $html);
        $this->assertStringContainsString('In progress', $html);
        // One month in progress, not one per year.
        $this->assertSame(1, substr_count($html, 'In progress'));
    }

    public function test_each_month_links_to_its_own_page(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        $this->assertStringContainsString('/admin/accounting/sales/2026-01', $this->list());
        $this->assertStringContainsString('/admin/accounting/purchases/2026-01', $this->list('purchases'));
    }

    public function test_a_month_page_names_its_period(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->assertSee('March 2026')
            ->assertSee('1 March to 31 March 2026')
            ->assertSee('No sales this month.');
    }

    public function test_a_month_outside_the_period_is_a_404(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        $admin = User::factory()->admin()->create();

        // Before the first month counted, and in a future that took nothing in.
        $this->actingAs($admin)->get('/admin/accounting/sales/2025-12')->assertNotFound();
        $this->actingAs($admin)->get('/admin/accounting/sales/2026-09')->assertNotFound();
        $this->actingAs($admin)->get('/admin/accounting/purchases/2025-12')->assertNotFound();
    }

    public function test_a_malformed_month_is_a_404(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['2026-13', 'janvier', '2026-1', '2026-01-01'] as $wrong) {
            $this->actingAs($admin)->get('/admin/accounting/sales/'.$wrong)->assertNotFound();
        }
    }

    public function test_a_staff_admin_cannot_open_a_month(): void
    {
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/accounting/sales/2026-01')
            ->assertForbidden();
    }
}
