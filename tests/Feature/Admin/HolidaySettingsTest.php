<?php

namespace Tests\Feature\Admin;

use App\Models\Holiday;
use App\Models\User;
use App\Support\ShippingEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Settings > Holidays: closures that ship dates step over, and that the
 * storefront announces ahead of time.
 */
class HolidaySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function closed(string $from, string $to): Holiday
    {
        return Holiday::query()->create(['starts_on' => $from, 'ends_on' => $to]);
    }

    public function test_an_admin_adds_and_removes_a_holiday(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/settings/holidays', ['starts_on' => '2026-12-24', 'ends_on' => '2027-01-03'])
            ->assertRedirect(route('admin.settings.holidays.index'));

        $holiday = Holiday::query()->sole();
        $this->assertSame('2027-01-03', $holiday->ends_on->toDateString());

        $this->actingAs($admin)->get('/admin/settings/holidays')
            ->assertOk()
            ->assertSee('Holidays')
            // The 3rd is a Sunday: parcels leave again on Monday the 4th.
            ->assertSee('Mon 4 Jan 2027');

        $this->actingAs($admin)
            ->delete('/admin/settings/holidays/'.$holiday->id)
            ->assertRedirect();
        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_each_line_opens_an_edit_dialog_with_its_dates(): void
    {
        $holiday = $this->closed('2026-12-24', '2027-01-03');

        $this->actingAs($this->admin())->get('/admin/settings/holidays')
            ->assertOk()
            ->assertSee('data-modal-open="holiday-edit-'.$holiday->id.'"', false)
            ->assertSee('<dialog id="holiday-edit-'.$holiday->id.'"', false)
            ->assertSee('value="2026-12-24"', false)
            ->assertSee('value="2027-01-03"', false);
    }

    public function test_an_admin_changes_a_holidays_dates(): void
    {
        $holiday = $this->closed('2026-12-24', '2027-01-03');

        $this->actingAs($this->admin())
            ->put('/admin/settings/holidays/'.$holiday->id, ['starts_on' => '2026-12-23', 'ends_on' => '2027-01-04'])
            ->assertRedirect(route('admin.settings.holidays.index'));

        $holiday->refresh();
        $this->assertSame('2026-12-23', $holiday->starts_on->toDateString());
        $this->assertSame('2027-01-04', $holiday->ends_on->toDateString());
    }

    public function test_a_refused_edit_reopens_its_dialog_with_the_error(): void
    {
        $holiday = $this->closed('2026-12-24', '2027-01-03');
        $admin = $this->admin();
        $form = 'holiday-edit-'.$holiday->id;

        $page = $this->actingAs($admin)
            ->from('/admin/settings/holidays')
            ->followingRedirects()
            ->put('/admin/settings/holidays/'.$holiday->id, ['_form' => $form, 'starts_on' => '2026-12-24', 'ends_on' => '2026-12-20'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<dialog id="'.$form.'" class="modal" aria-labelledby="'.$form.'-title"  data-autoopen', $page);
        $this->assertSame(1, substr_count($page, 'class="form-error"'));
        $this->assertSame('2027-01-03', $holiday->refresh()->ends_on->toDateString());
    }

    public function test_the_last_day_cannot_come_before_the_first(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/holidays', ['starts_on' => '2026-12-24', 'ends_on' => '2026-12-20'])
            ->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_the_settings_page_links_to_holidays(): void
    {
        $this->actingAs($this->admin())->get('/admin/settings')
            ->assertOk()
            ->assertSee(route('admin.settings.holidays.index'));
    }

    public function test_the_ship_date_steps_over_a_closure_and_the_weekend_after_it(): void
    {
        $this->closed('2026-12-24', '2026-12-25');

        // Thursday the 24th, early: the shop is closed through Friday, so Monday.
        $date = ShippingEstimate::standard(Carbon::parse('2026-12-24 08:00', 'Europe/Paris'));

        $this->assertSame('2026-12-28', $date->toDateString());
    }

    public function test_a_weekend_running_into_a_closure_lands_after_both(): void
    {
        $this->closed('2026-12-28', '2026-12-29');

        // Friday afternoon: Monday and Tuesday are closed, so Wednesday.
        $date = ShippingEstimate::standard(Carbon::parse('2026-12-25 15:00', 'Europe/Paris'));

        $this->assertSame('2026-12-30', $date->toDateString());
    }

    public function test_a_backordered_estimate_steps_over_a_closure_too(): void
    {
        $this->closed('2026-12-21', '2026-12-31');

        $date = ShippingEstimate::backordered(Carbon::parse('2026-12-14 09:00', 'Europe/Paris'), 8);

        $this->assertSame('2027-01-01', $date->toDateString());
    }

    public function test_the_shop_announces_a_closure_ahead_of_time(): void
    {
        $this->travelTo(Carbon::parse('2026-12-15 12:00', 'Europe/Paris'));
        $this->closed('2026-12-24', '2027-01-03');

        $this->get('/')
            ->assertOk()
            ->assertSee('ship-strip--holiday', false)
            ->assertSee('<b class="ship-strip-amount">24 décembre</b> au <b class="ship-strip-amount">3 janvier</b>', false)
            ->assertSee('reprise des envois le lundi 4 janvier');
    }

    public function test_the_notice_starts_exactly_fourteen_days_ahead(): void
    {
        $this->travelTo(Carbon::parse('2026-12-10 12:00', 'Europe/Paris'));
        $this->closed('2026-12-24', '2027-01-03');

        $this->get('/')->assertOk()->assertSee('ship-strip--holiday', false);
    }

    public function test_a_closure_too_far_off_or_over_is_not_announced(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 12:00', 'Europe/Paris'));
        $this->closed('2026-12-24', '2027-01-03');
        $this->closed('2026-11-01', '2026-11-02');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('ship-strip--holiday', false);
    }

    public function test_a_single_day_closure_reads_as_one_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00', 'Europe/Paris'));
        $this->closed('2026-11-11', '2026-11-11');

        $this->get('/')
            ->assertOk()
            ->assertSee('Commandes ouvertes, envois suspendus le <b class="ship-strip-amount">11 novembre</b>', false)
            ->assertSee('reprise des envois le jeudi 12 novembre');
    }
}
