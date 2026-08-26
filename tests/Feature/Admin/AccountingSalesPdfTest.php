<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AccountingController;
use App\Models\AccountingEntry;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A month's journal of sales, as a PDF. */
class AccountingSalesPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
        $this->travelTo('2026-08-26 10:00:00');
    }

    /** A real sale, placed on the given date. `created_at` is forced: it is not fillable. */
    /**
     * A real sale placed on the given date.
     *
     * `created_at` is forced after creation: it is not fillable, so Eloquent
     * would otherwise stamp it with now and the sale would land in the wrong
     * month.
     */
    private function order(string $placedAt, array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create(['first_name' => 'Camille', 'last_name' => 'Roy'])->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 10000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 10000, 'payment_method' => 'card',
        ], $overrides));

        $order->forceFill(['created_at' => $placedAt])->save();

        return $order->refresh();
    }

    /** A hand-written entry, on the given date. */
    private function entry(string $on, array $overrides = []): AccountingEntry
    {
        return AccountingEntry::query()->create(array_merge([
            'section' => 'sales',
            'entered_on' => $on,
            'invoice_number' => 'INV-2026-014',
            'client' => 'Club de Nantes',
            'type' => 'prestation',
            'total_cents' => 24000,
            'fees_cents' => 1250,
            'payment_method' => 'bank_wire',
            'remark' => 'Initiation',
        ], $overrides));
    }

    /** The journal's HTML, before the renderer turns it into a PDF. */
    private function render(string $month = '2026-03'): string
    {
        $period = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        // The data comes from the controller rather than being recomputed
        // here: otherwise the test would check its own arithmetic.
        $controller = app(AccountingController::class);
        $method = new \ReflectionMethod($controller, 'journalData');
        $method->setAccessible(true);

        return view('admin.accounting.sales-pdf', $method->invoke($controller, $period))->render();
    }

    public function test_the_month_downloads_under_its_own_name(): void
    {
        $this->order('2026-03-12 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('ventes-2026-03.pdf');
    }

    public function test_the_page_offers_the_download(): void
    {
        // A month has to hold a line before it has a sheet to offer.
        $this->order('2026-03-12 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->assertSee('/admin/accounting/sales/2026-03/pdf', false)
            ->assertSee('Download PDF');
    }

    public function test_the_journal_carries_orders_and_hand_written_entries(): void
    {
        $order = $this->order('2026-03-12 09:00:00');
        $this->entry('2026-03-14');

        $html = $this->render();

        $this->assertStringContainsString('Journal des ventes', $html);
        $this->assertStringContainsString('INV-'.$order->number, $html);
        $this->assertStringContainsString('Camille ROY', $html);
        $this->assertStringContainsString('INV-2026-014', $html);
        $this->assertStringContainsString('Club de Nantes', $html);
        $this->assertStringContainsString('Saisie', $html);
    }

    public function test_the_totals_match_the_screen(): void
    {
        $this->order('2026-03-02 09:00:00', ['payment_fee_cents' => 250]);
        $this->entry('2026-03-14');

        $html = $this->render();

        // 100 € + 240 € = 340 €; 2,50 € + 12,50 € of fees; 325 € perceived.
        $this->assertStringContainsString('340,00', $html);
        $this->assertStringContainsString('15,00', $html);
        $this->assertStringContainsString('325,00', $html);
    }

    public function test_a_refund_is_written_down_but_left_out_of_the_total(): void
    {
        $refunded = $this->order('2026-03-05 09:00:00', ['status' => 'refunded', 'total_cents' => 4000]);
        $this->order('2026-03-06 09:00:00', ['total_cents' => 10000]);

        $html = $this->render();

        $this->assertStringContainsString('INV-'.$refunded->number, $html);
        // The struck-through invoice is enough: a label would repeat the strike.
        $this->assertStringNotContainsString('Remboursé', $html);
        $this->assertMatchesRegularExpression('/tr\.refunded \.col-invoice\s*\{[^}]*line-through/s', $html);
        $this->assertStringContainsString('<tr class="refunded">', $html);
        $this->assertStringContainsString('1 remboursement hors total', $html);
        // 100 € alone: the 40 € refunded do not add on.
        $this->assertStringContainsString('100,00', $html);
        $this->assertStringNotContainsString('140,00', $html);
    }

    public function test_the_sheet_can_be_signed(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        $company = CompanySetting::current()->value('company_name');

        // The journal belongs to the company, not to the shop sign.
        $this->assertStringContainsString('class="signature"', $html);
        $this->assertStringContainsString('Pour '.$company, $html);
        $this->assertStringNotContainsString('Pour ArmoOutdoor', $html);
        $this->assertStringContainsString('<div class="sign-rule"></div>', $html);
    }

    public function test_only_that_month_is_on_the_sheet(): void
    {
        $inside = $this->order('2026-03-31 23:30:00');
        $outside = $this->order('2026-04-01 00:30:00');

        $html = $this->render();

        $this->assertStringContainsString('INV-'.$inside->number, $html);
        $this->assertStringNotContainsString('INV-'.$outside->number, $html);
    }

    public function test_a_month_outside_the_period_has_no_sheet(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/accounting/sales/2025-12/pdf')->assertNotFound();
        $this->actingAs($admin)->get('/admin/accounting/sales/2026-09/pdf')->assertNotFound();
    }

    public function test_a_staff_admin_cannot_download_it(): void
    {
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/accounting/sales/2026-03/pdf')
            ->assertForbidden();
    }

    public function test_the_sheet_is_landscape(): void
    {
        $this->order('2026-03-12 09:00:00');

        $pdf = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03/pdf')
            ->assertOk()
            ->getContent();

        // A4 on its side: 842 points wide. Upright, the ten columns would
        // fold onto one another.
        preg_match('/MediaBox\s*\[[\d.\s]*?([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $box);

        $this->assertNotEmpty($box, 'Pas de format de page dans le PDF.');
        $this->assertGreaterThan((float) $box[2], (float) $box[1]);
    }

    public function test_the_brand_never_appears_on_the_journal(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();
        $company = CompanySetting::current()->value('company_name');

        // This is a company document: neither the shop's name nor its
        // signature belongs on the accounting book.
        $this->assertStringNotContainsString('ArmoOutdoor', $html);
        $this->assertStringNotContainsString('Stand et terrain', $html);
        $this->assertStringContainsString($company, $html);
    }

    public function test_the_remark_column_is_gone(): void
    {
        $this->entry('2026-03-14', ['remark' => 'Initiation du samedi']);

        $html = $this->render();

        // The remark crowded ten columns that were already tight.
        $this->assertStringNotContainsString('Remarque', $html);
        $this->assertStringNotContainsString('Initiation du samedi', $html);
    }

    public function test_the_totals_carry_their_own_headings(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();
        $foot = substr($html, strpos($html, '<tfoot>'));

        // On a long page, the bottom of the table reads without going back up.
        $this->assertSame(3, substr_count($foot, 'foot-label'));
        $this->assertStringContainsString('>Total<', $foot);
        $this->assertStringContainsString('>Frais<', $foot);
        $this->assertStringContainsString('>Perçu<', $foot);
    }

    public function test_the_sheet_speaks_french_throughout(): void
    {
        $this->order('2026-03-12 09:00:00');
        $this->entry('2026-03-14', ['type' => 'repair', 'payment_method' => 'cheque']);

        $html = $this->render();

        // The month, the kind and the payment: a French accounting book must
        // not keep words from the admin screen.
        // The month carries a capital: it is a title, not a sentence.
        $this->assertStringContainsString('Mars 2026', $html);
        $this->assertStringNotContainsString('mars 2026', $html);
        $this->assertStringContainsString('Vente sur stock', $html);
        $this->assertStringContainsString('Réparation', $html);
        $this->assertStringContainsString('Chèque', $html);

        foreach (['March 2026', 'Stock sale', 'Repair', 'Cheque', 'Bank wire'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }
    }

    public function test_the_payment_column_stands_apart_from_the_amounts(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        // The payment ends at the right edge of the table, the way the date
        // starts at the left one: the alignment is what sets it apart from the
        // perceived figure, with no rule and no empty column.
        $this->assertMatchesRegularExpression('/\.col-payment\s*\{[^}]*text-align:\s*right/s', $html);
        $this->assertDoesNotMatchRegularExpression('/\.col-payment\s*\{[^}]*border-left:\s*[^0]/s', $html);
        $this->assertStringNotContainsString('col-gap', $html);
    }

    public function test_the_columns_fit_the_page(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        preg_match_all('/\.col-[a-z-]+\s*\{[^}]*width:\s*(\d+)%/s', $html, $matches);

        // `col-money` serves two columns: its width counts twice.
        $widths = array_map('intval', $matches[1]);
        $total = array_sum($widths) + 9;

        $this->assertLessThanOrEqual(100, $total, 'Les colonnes débordent de la page.');
    }

    public function test_an_accented_month_is_capitalised_intact(): void
    {
        $this->order('2026-08-03 09:00:00');

        $html = $this->render('2026-08');

        // The month is written out in full and keeps its accent once capitalised.
        $this->assertStringContainsString('Août 2026', $html);
        $this->assertStringContainsString('1 Août au 31 Août 2026', $html);
    }

    public function test_a_long_client_name_stays_on_one_line(): void
    {
        $order = $this->order('2026-03-12 09:00:00');
        $order->user->update(['first_name' => 'Briek', 'last_name' => 'Vancompernolle']);

        $html = $this->render();

        // The column has to hold the longest name of the month without
        // wrapping it: one row of double height throws off the whole table.
        $this->assertStringContainsString('Briek VANCOMPERNOLLE', $html);
        preg_match('/\.col-client\s*\{[^}]*width:\s*(\d+)%/s', $html, $width);
        $this->assertGreaterThanOrEqual(15, (int) $width[1]);
    }

    public function test_the_month_in_progress_has_no_sheet(): void
    {
        // It is August: the month is still taking money in, and two printings
        // of the same journal would not agree.
        $this->order('2026-08-03 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-08/pdf')
            ->assertNotFound();
    }

    public function test_the_button_is_greyed_out_until_the_month_ends(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('2026-07-12 09:00:00');
        $this->order('2026-08-03 09:00:00');

        $running = $this->actingAs($admin)->get('/admin/accounting/sales/2026-08')->assertOk();
        $running->assertSee('is-disabled', false)
            ->assertSee('Available once the month has ended')
            ->assertDontSee('/admin/accounting/sales/2026-08/pdf', false);

        // A closed month keeps its button alive.
        $closed = $this->actingAs($admin)->get('/admin/accounting/sales/2026-07')->assertOk();
        $closed->assertSee('/admin/accounting/sales/2026-07/pdf', false)
            ->assertDontSee('is-disabled', false);
    }

    public function test_the_sheet_carries_the_company_address_whatever_the_settings_say(): void
    {
        CompanySetting::current()->update(['contact_email' => 'boutique@armooutdoor.fr']);

        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        // The journal is a company document: the shop contact can change in
        // the settings without the accounting book following.
        $this->assertStringContainsString('hello@swiftshelf.fr', $html);
        $this->assertStringNotContainsString('boutique@armooutdoor.fr', $html);
    }

    public function test_an_empty_month_cannot_be_downloaded(): void
    {
        $admin = User::factory()->admin()->create();

        // March sold nothing: an empty sheet is not a document.
        $this->actingAs($admin)
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->assertSee('is-disabled', false)
            ->assertSee('Nothing to print for this month')
            ->assertDontSee('/admin/accounting/sales/2026-03/pdf', false);

        $this->actingAs($admin)->get('/admin/accounting/sales/2026-03/pdf')->assertNotFound();
    }

    public function test_a_hand_written_entry_is_enough_to_print_a_month(): void
    {
        $this->entry('2026-03-14');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03/pdf')
            ->assertOk()
            ->assertDownload('ventes-2026-03.pdf');
    }
}
