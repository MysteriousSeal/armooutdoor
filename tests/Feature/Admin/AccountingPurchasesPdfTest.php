<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AccountingController;
use App\Models\AccountingEntry;
use App\Models\AccountingJournalDownload;
use App\Models\CompanySetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A month's journal of purchases, as a PDF. */
class AccountingPurchasesPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-26 10:00:00');
    }

    /** A purchase on the given date. */
    private function entry(string $on, array $overrides = []): AccountingEntry
    {
        return AccountingEntry::query()->create(array_merge([
            'section' => 'purchases',
            'entered_on' => $on,
            'invoice_number' => 'F-2026-0142',
            'client' => 'DM Diffusion',
            'type' => 'achat stock',
            'total_cents' => 12000,
            'vat_rate_basis_points' => 2000,
            'fees_cents' => 0,
            'payment_method' => 'bank_wire',
        ], $overrides));
    }

    /** The journal's HTML, before the renderer turns it into a PDF. */
    private function render(string $month = '2026-07'): string
    {
        $period = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        // The data comes from the controller rather than being recomputed here:
        // otherwise the test would check its own arithmetic.
        $controller = app(AccountingController::class);
        $rows = new \ReflectionMethod($controller, 'purchaseRowsOf');
        $rows->setAccessible(true);
        $data = new \ReflectionMethod($controller, 'purchaseJournalData');
        $data->setAccessible(true);

        return view(
            'admin.accounting.purchases-pdf',
            $data->invoke($controller, $period, $rows->invoke($controller, $period)),
        )->render();
    }

    public function test_the_month_downloads_under_its_own_name(): void
    {
        $this->entry('2026-07-14');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-07/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('achats-2026-07.pdf');
    }

    public function test_the_journal_carries_the_lines_and_their_three_amounts(): void
    {
        $this->entry('2026-07-14');

        $html = $this->render();

        $this->assertStringContainsString('Journal des achats', $html);
        $this->assertStringContainsString('F-2026-0142', $html);
        $this->assertStringContainsString('DM Diffusion', $html);
        $this->assertStringContainsString('achat stock', $html);
        // 120 € paid at 20%: 100 € before tax, 20 € of tax.
        $this->assertStringContainsString('100,00', $html);
        $this->assertStringContainsString('20,00', $html);
        $this->assertStringContainsString('120,00', $html);
        $this->assertStringContainsString('20%', $html);
    }

    public function test_the_sheet_speaks_french_like_the_sales_journal(): void
    {
        $this->entry('2026-07-14', ['payment_method' => 'cheque']);

        $html = $this->render();

        $this->assertStringContainsString('Juillet 2026', $html);
        $this->assertStringContainsString('Fournisseur', $html);
        $this->assertStringContainsString('Chèque', $html);

        foreach (['July 2026', 'Supplier', 'Cheque', 'Bank wire'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }
    }

    public function test_the_totals_match_the_screen(): void
    {
        $this->entry('2026-07-02', ['total_cents' => 12000]);
        $this->entry('2026-07-20', ['total_cents' => 6000]);

        $html = $this->render();
        $foot = substr($html, strpos($html, '<tfoot>'));

        // 180 € paid, 150 € before tax, 30 € of tax.
        $this->assertStringContainsString('150,00', $foot);
        $this->assertStringContainsString('30,00', $foot);
        $this->assertStringContainsString('180,00', $foot);
    }

    public function test_the_sheet_belongs_to_the_company_and_can_be_signed(): void
    {
        $this->entry('2026-07-14');

        $html = $this->render();

        $this->assertStringContainsString('Pour '.CompanySetting::current()->value('company_name'), $html);
        $this->assertStringContainsString('hello@swiftshelf.fr', $html);
        $this->assertStringNotContainsString('ArmoOutdoor', $html);
        $this->assertStringContainsString('<div class="sign-rule"></div>', $html);
    }

    public function test_only_that_month_is_on_the_sheet(): void
    {
        $this->entry('2026-07-31', ['invoice_number' => 'F-DEDANS']);
        $this->entry('2026-06-30', ['invoice_number' => 'F-AVANT']);

        $html = $this->render();

        $this->assertStringContainsString('F-DEDANS', $html);
        $this->assertStringNotContainsString('F-AVANT', $html);
    }

    public function test_a_sale_never_reaches_the_purchase_journal(): void
    {
        $this->entry('2026-07-14');
        AccountingEntry::query()->create([
            'section' => 'sales',
            'entered_on' => '2026-07-15',
            'invoice_number' => 'INV-VENTE',
            'type' => 'prestation',
            'total_cents' => 24000,
            'fees_cents' => 0,
            'payment_method' => 'bank_wire',
        ]);

        $this->assertStringNotContainsString('INV-VENTE', $this->render());
    }

    public function test_a_month_with_nothing_to_print_has_no_sheet(): void
    {
        $admin = User::factory()->admin()->create();

        // Nothing bought in July, and August is still running.
        $this->actingAs($admin)->get('/admin/accounting/purchases/2026-07/pdf')->assertNotFound();

        $this->entry('2026-08-03');
        $this->actingAs($admin)->get('/admin/accounting/purchases/2026-08/pdf')->assertNotFound();
    }

    public function test_the_download_is_written_down_like_a_sales_one(): void
    {
        $this->entry('2026-07-14');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/purchases/2026-07/pdf')->assertOk();

        $download = AccountingJournalDownload::query()->sole();
        $this->assertSame('purchases', $download->section);
        $this->assertSame('2026-07', $download->month);
        $this->assertSame($owner->id, $download->user_id);
        $this->assertNotNull($download->fingerprint);
    }

    public function test_the_month_page_reports_the_last_copy(): void
    {
        $this->entry('2026-07-14');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->get('/admin/accounting/purchases/2026-07')
            ->assertOk()
            ->assertSee('Never downloaded');

        $this->actingAs($owner)->get('/admin/accounting/purchases/2026-07/pdf')->assertOk();

        $this->actingAs($owner)
            ->get('/admin/accounting/purchases/2026-07')
            ->assertOk()
            ->assertSee('Last downloaded')
            ->assertDontSee('Never downloaded');
    }

    public function test_a_changed_line_makes_the_filed_copy_out_of_date(): void
    {
        $entry = $this->entry('2026-07-14');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/purchases/2026-07/pdf')->assertOk();
        $entry->update(['total_cents' => 24000]);

        $this->actingAs($owner)
            ->get('/admin/accounting/purchases/2026-07')
            ->assertOk()
            ->assertSee('Changed since the copy of');
    }

    public function test_a_remark_the_journal_never_prints_raises_nothing(): void
    {
        $entry = $this->entry('2026-07-14');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/purchases/2026-07/pdf')->assertOk();
        $entry->update(['remark' => 'facture reçue par courrier']);

        $this->actingAs($owner)
            ->get('/admin/accounting/purchases/2026-07')
            ->assertOk()
            ->assertDontSee('Changed since');
    }

    public function test_the_month_list_marks_what_is_filed_and_what_moved(): void
    {
        $this->entry('2026-07-14');
        $july = AccountingEntry::query()->sole();
        $this->entry('2026-06-10');
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/purchases/2026-07/pdf')->assertOk();
        $this->actingAs($owner)->get('/admin/accounting/purchases/2026-06/pdf')->assertOk();
        $july->update(['total_cents' => 30000]);

        $html = $this->actingAs($owner)
            ->get('/admin/accounting/purchases')
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'accounting-month-filed is-stale'));
        $this->assertMatchesRegularExpression('#2026-07.*?accounting-month-filed is-stale#s', $html);
    }

    public function test_a_staff_admin_cannot_download_it(): void
    {
        $this->entry('2026-07-14');

        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/accounting/purchases/2026-07/pdf')
            ->assertForbidden();
    }
}
