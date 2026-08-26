<?php

namespace Tests\Feature\Admin;

use App\Models\AccountingEntry;
use App\Models\AccountingJournalDownload;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every journal taken out of the admin leaves a trace. */
class AccountingDownloadLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
        $this->travelTo('2026-08-26 10:00:00');
    }

    /** A real sale, so the month has something to print. */
    private function order(string $placedAt): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 10000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 10000, 'payment_method' => 'card',
        ]);

        $order->forceFill(['created_at' => $placedAt])->save();

        return $order->refresh();
    }

    public function test_a_download_is_written_down_with_who_and_when(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create(['first_name' => 'Colas', 'last_name' => 'Durand']);

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        $download = AccountingJournalDownload::query()->sole();
        $this->assertSame('sales', $download->section);
        $this->assertSame('2026-04', $download->month);
        $this->assertSame($owner->id, $download->user_id);
        $this->assertTrue($download->created_at->isSameMinute(now()));
    }

    public function test_a_month_never_taken_out_says_so(): void
    {
        $this->order('2026-04-12 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Never downloaded')
            ->assertDontSee('Last downloaded');
    }

    public function test_the_month_page_reports_the_last_download(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create(['first_name' => 'Colas', 'last_name' => 'Durand']);

        $this->travelTo('2026-08-12 14:32:00');
        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Last downloaded 12 Aug 2026 at 14:32')
            ->assertSee('by Colas DURAND')
            ->assertDontSee('Never downloaded');
    }

    public function test_a_second_download_is_a_second_row_and_the_newest_wins(): void
    {
        $this->order('2026-04-12 09:00:00');
        $first = User::factory()->admin()->create(['first_name' => 'Colas', 'last_name' => 'Durand']);
        $second = User::factory()->admin()->create(['first_name' => 'Manon', 'last_name' => 'Leroy']);

        $this->travelTo('2026-08-12 14:32:00');
        $this->actingAs($first)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        // A copy taken a fortnight later is its own event, not an overwrite.
        $this->travelTo('2026-08-25 09:05:00');
        $this->actingAs($second)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        $this->assertSame(2, AccountingJournalDownload::query()->count());

        $this->actingAs($first)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('25 Aug 2026 at 09:05')
            ->assertSee('by Manon LEROY');
    }

    public function test_the_month_list_ticks_what_has_been_filed(): void
    {
        $this->order('2026-04-12 09:00:00');
        $this->order('2026-05-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        $html = $this->actingAs($owner)
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        // April carries the tick, May does not.
        $this->assertSame(1, substr_count($html, 'accounting-month-filed'));
        $this->assertMatchesRegularExpression('#2026-04.*?accounting-month-filed#s', $html);
    }

    public function test_a_refused_download_leaves_no_trace(): void
    {
        $this->order('2026-08-03 09:00:00');

        // The month is still running, so the PDF is refused.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-08/pdf')
            ->assertNotFound();

        $this->assertSame(0, AccountingJournalDownload::query()->count());
    }

    public function test_the_trace_survives_the_admin_being_deleted(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();
        $owner->delete();

        // Losing who took it must not lose that it was taken.
        $download = AccountingJournalDownload::query()->sole();
        $this->assertNull($download->fresh()->user_id);
    }

    /** Adds a hand-written entry to a month. */
    private function entry(string $on, array $overrides = []): AccountingEntry
    {
        return AccountingEntry::query()->create(array_merge([
            'section' => 'sales',
            'entered_on' => $on,
            'type' => 'prestation',
            'total_cents' => 24000,
            'fees_cents' => 0,
            'payment_method' => 'bank_wire',
        ], $overrides));
    }

    public function test_a_month_untouched_since_its_download_says_nothing(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Last downloaded')
            ->assertDontSee('Changed since');
    }

    public function test_a_new_entry_makes_the_filed_copy_out_of_date(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();
        $this->entry('2026-04-20');

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Changed since the copy of')
            ->assertSee('download it again');
    }

    public function test_a_deleted_entry_counts_as_a_change(): void
    {
        $this->order('2026-04-12 09:00:00');
        $entry = $this->entry('2026-04-20');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        // A removed line touches no date anywhere: only the printed figures
        // can tell that the month has moved.
        $entry->delete();

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Changed since the copy of');
    }

    public function test_a_refund_makes_the_filed_copy_out_of_date(): void
    {
        $order = $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();
        $order->update(['status' => 'refunded']);

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Changed since the copy of');
    }

    public function test_a_change_the_journal_never_prints_raises_nothing(): void
    {
        $order = $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        // The tracking number appears nowhere on the journal: telling anyone to
        // reprint an identical sheet would teach the warning to be ignored.
        $order->update(['tracking_number' => '6A11122233344']);

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Last downloaded')
            ->assertDontSee('Changed since');
    }

    public function test_downloading_again_settles_the_warning(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();
        $this->entry('2026-04-20');
        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertDontSee('Changed since');
    }

    public function test_the_list_marks_the_month_that_moved(): void
    {
        $this->order('2026-04-12 09:00:00');
        $this->order('2026-05-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();
        $this->actingAs($owner)->get('/admin/accounting/sales/2026-05/pdf')->assertOk();
        $this->entry('2026-04-20');

        $html = $this->actingAs($owner)
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        // April warns, May keeps its plain tick.
        $this->assertSame(1, substr_count($html, 'accounting-month-filed is-stale'));
        $this->assertMatchesRegularExpression('#2026-04.*?accounting-month-filed is-stale#s', $html);
    }

    public function test_a_download_from_before_fingerprints_never_cries_wolf(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();
        AccountingJournalDownload::query()->update(['fingerprint' => null]);
        $this->entry('2026-04-20');

        // Nothing is known about that copy, so nothing is claimed about it.
        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertDontSee('Changed since');
    }

    public function test_a_changed_fee_makes_the_filed_copy_out_of_date(): void
    {
        $order = $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        // The total does not move, only what is held back from it — and the
        // perceived figure follows.
        $order->update(['payment_fee_cents' => 250]);

        $this->actingAs($owner)
            ->get('/admin/accounting/sales/2026-04')
            ->assertOk()
            ->assertSee('Changed since the copy of');
    }

    public function test_a_month_waiting_to_be_filed_says_so(): void
    {
        $this->order('2026-04-12 09:00:00');

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#April.*?Download available#s', $html);
    }

    public function test_an_empty_month_is_never_offered(): void
    {
        $this->order('2026-04-12 09:00:00');

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        // Only April sold anything: the other months have nothing to print.
        $this->assertSame(1, substr_count($html, 'Download available'));
    }

    public function test_the_running_month_is_never_offered(): void
    {
        // August is the month in progress and cannot be ruled off yet.
        $this->order('2026-08-03 09:00:00');

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Download available', $html);
    }

    public function test_the_offer_disappears_once_the_month_is_filed(): void
    {
        $this->order('2026-04-12 09:00:00');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales/2026-04/pdf')->assertOk();

        $html = $this->actingAs($owner)
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Download available', $html);
        $this->assertStringContainsString('Downloaded', $html);
    }

    public function test_the_offer_chip_wears_the_admin_blue_in_both_themes(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));

        // The same blue as the other "something to do" chips, so the three
        // states of a month read as one family: blue to file, green filed,
        // amber out of date.
        $this->assertMatchesRegularExpression(
            '/\.accounting-month-available\s*\{[^}]*color:\s*#2f5d8a/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            "/\[data-theme='dark'\]\s*\.accounting-month-available\s*\{[^}]*#8ab4dd/s",
            $css
        );
    }
}
