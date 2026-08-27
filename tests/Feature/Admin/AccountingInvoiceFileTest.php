<?php

namespace Tests\Feature\Admin;

use App\Models\AccountingEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The supplier's invoice, attached to the purchase it paid for. */
class AccountingInvoiceFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-08-27 10:00:00');
    }

    /** A purchase to hang a file on. */
    private function entry(array $overrides = []): AccountingEntry
    {
        return AccountingEntry::query()->create(array_merge([
            'section' => 'purchases',
            'entered_on' => '2026-06-10',
            'invoice_number' => 'FV-26005188',
            'client' => 'GS1',
            'type' => 'Achat GTIN',
            'total_cents' => 26880,
            'vat_rate_basis_points' => 2000,
            'fees_cents' => 0,
            'payment_method' => 'card',
        ], $overrides));
    }

    /** A real PDF, since the upload is checked on its contents, not its name. */
    private function pdf(string $name = 'facture.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function upload(AccountingEntry $entry, ?UploadedFile $file = null, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? User::factory()->admin()->create())
            ->post('/admin/accounting/purchases/2026-06/entries/'.$entry->id.'/invoice', [
                'invoice_file' => $file ?? $this->pdf(),
            ]);
    }

    public function test_an_invoice_can_be_attached_to_a_line(): void
    {
        $entry = $this->entry();

        $this->upload($entry)->assertRedirect('/admin/accounting/purchases/2026-06');

        $entry->refresh();
        $this->assertNotNull($entry->invoice_path);
        Storage::assertExists($entry->invoice_path);
    }

    public function test_the_file_never_lands_in_the_public_directory(): void
    {
        $entry = $this->entry();
        $this->upload($entry);

        // Anything under public/ is readable by whoever guesses its name.
        $path = $entry->refresh()->invoice_path;
        $this->assertStringStartsWith('accounting/invoices/', $path);
        $this->assertFileDoesNotExist(public_path($path));
    }

    public function test_the_file_is_named_after_the_supplier_and_the_invoice(): void
    {
        $this->assertSame('gs1_fv-26005188.pdf', $this->entry()->invoiceFileName());
    }

    public function test_a_line_without_a_number_falls_back_to_its_date(): void
    {
        $entry = $this->entry(['invoice_number' => null, 'client' => 'Packlink', 'entered_on' => '2026-06-01']);

        $this->assertSame('packlink_2026-06-01.pdf', $entry->invoiceFileName());
    }

    public function test_the_pdf_opens_in_the_tab_rather_than_downloading(): void
    {
        $entry = $this->entry();
        $this->upload($entry);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06/entries/'.$entry->id.'/invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="gs1_fv-26005188.pdf"');
    }

    public function test_only_the_owner_can_read_the_file(): void
    {
        $entry = $this->entry();
        $this->upload($entry);

        $url = '/admin/accounting/purchases/2026-06/entries/'.$entry->id.'/invoice';

        // `actingAs` sticks for the rest of the test, and the upload above ran
        // as the owner: without this the "guest" request would still be signed
        // in as them.
        auth()->logout();

        // A guest, a customer and a staff admin are all turned away.
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->get($url)->assertRedirect();
        $this->actingAs(User::factory()->staffAdmin()->create())->get($url)->assertForbidden();
    }

    public function test_a_staff_admin_cannot_attach_one_either(): void
    {
        $entry = $this->entry();

        $this->upload($entry, null, User::factory()->staffAdmin()->create())->assertForbidden();

        $this->assertNull($entry->refresh()->invoice_path);
    }

    public function test_uploading_again_replaces_the_file_and_removes_the_old_one(): void
    {
        $entry = $this->entry();
        $this->upload($entry);
        $first = $entry->refresh()->invoice_path;

        $this->upload($entry, $this->pdf('autre.pdf'));
        $second = $entry->refresh()->invoice_path;

        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);
    }

    public function test_an_invoice_can_be_detached(): void
    {
        $entry = $this->entry();
        $this->upload($entry);
        $path = $entry->refresh()->invoice_path;

        $this->actingAs(User::factory()->admin()->create())
            ->delete('/admin/accounting/purchases/2026-06/entries/'.$entry->id.'/invoice')
            ->assertRedirect('/admin/accounting/purchases/2026-06');

        $this->assertNull($entry->refresh()->invoice_path);
        Storage::assertMissing($path);
    }

    public function test_anything_but_a_pdf_is_refused(): void
    {
        $entry = $this->entry();

        // A real PNG, named .pdf and claiming to be one. The check reads the
        // file itself, so the disguise does not get through.
        $path = tempnam(sys_get_temp_dir(), 'png').'.pdf';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $disguised = new UploadedFile($path, 'facture.pdf', 'application/pdf', null, true);

        $this->upload($entry, $disguised)->assertSessionHasErrors('invoice_file');
        $this->assertNull($entry->refresh()->invoice_path);
    }

    public function test_a_line_with_no_file_has_nothing_to_show(): void
    {
        $entry = $this->entry();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06/entries/'.$entry->id.'/invoice')
            ->assertNotFound();
    }

    public function test_a_sales_entry_carries_no_supplier_invoice(): void
    {
        $entry = $this->entry(['section' => 'sales', 'type' => 'prestation']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/accounting/sales/2026-06/entries/'.$entry->id.'/invoice', ['invoice_file' => $this->pdf()])
            ->assertNotFound();
    }

    public function test_the_month_page_offers_to_attach_and_then_to_open(): void
    {
        $entry = $this->entry();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->assertSee('Attach')
            ->assertDontSee('accounting-invoice-link', false);

        $this->upload($entry);

        $this->actingAs($admin)
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->assertSee('accounting-invoice-link', false)
            // Opened in a tab of its own, not in place of the table.
            ->assertSee('target="_blank"', false);
    }

    public function test_a_line_without_an_invoice_number_is_offered_nothing(): void
    {
        // Nothing to attach: no number means no paper behind the line.
        $this->entry(['invoice_number' => null, 'client' => 'Packlink']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->assertDontSee('Attach');
    }

    public function test_the_upload_url_refuses_a_line_without_a_number(): void
    {
        $entry = $this->entry(['invoice_number' => null]);

        // The page offers nothing, and the address answers the same way.
        $this->upload($entry)->assertNotFound();
        $this->assertNull($entry->refresh()->invoice_path);
    }

    public function test_the_month_counts_the_invoices_it_is_still_owed(): void
    {
        $this->entry(['invoice_number' => 'F-1']);
        $this->entry(['invoice_number' => 'F-2']);
        $attached = $this->entry(['invoice_number' => 'F-3']);
        // No number, so nothing can be attached: never counted as missing.
        $this->entry(['invoice_number' => null]);

        $this->upload($attached);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->assertSee('Invoices missing')
            ->assertSee('lines without their PDF')
            ->assertSee('accounting-missing-card', false)
            ->assertDontSee('All attached');
    }

    public function test_a_fully_documented_month_says_so(): void
    {
        $entry = $this->entry();
        $this->upload($entry);
        // A numberless line does not hold the month back.
        $this->entry(['invoice_number' => null, 'entered_on' => '2026-06-11']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->assertSee('All attached')
            ->assertSee('is-complete', false);
    }

    public function test_the_month_list_says_how_many_invoices_a_month_owes(): void
    {
        $this->entry(['invoice_number' => 'F-1']);
        $this->entry(['invoice_number' => 'F-2']);
        $attached = $this->entry(['invoice_number' => 'F-3']);
        // No number, so nothing to owe on that line.
        $this->entry(['invoice_number' => null]);
        $this->upload($attached);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases')
            ->assertOk()
            ->getContent();

        // Two lines still owe their PDF, beside the month's own name.
        $this->assertMatchesRegularExpression(
            '#accounting-month-name">\s*June.*?accounting-month-owed[^>]*>\s*2#s',
            $html
        );
    }

    public function test_a_month_with_every_invoice_on_file_shows_a_tick(): void
    {
        $entry = $this->entry();
        $this->upload($entry);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '#accounting-month-name">\s*June.*?accounting-month-owed is-complete#s',
            $html
        );
    }

    public function test_an_empty_month_is_marked_neither_way(): void
    {
        $this->entry();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases')
            ->assertOk()
            ->getContent();

        // Only June holds anything: a month with no line owes nothing and
        // claims nothing.
        $this->assertSame(1, substr_count($html, 'accounting-month-owed'));
    }

    public function test_the_sales_list_carries_no_such_marker(): void
    {
        $this->entry(['section' => 'sales', 'type' => 'prestation']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            // A sale has no supplier invoice to be owed.
            ->assertDontSee('accounting-month-owed', false);
    }

    public function test_detaching_asks_before_it_deletes(): void
    {
        $entry = $this->entry();
        $this->upload($entry);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->getContent();

        // The × opens the shared dialog rather than sending straight away.
        $this->assertStringContainsString('data-modal-open="invoice-delete-modal"', $html);
        $this->assertStringContainsString('<dialog id="invoice-delete-modal"', $html);
        $this->assertStringContainsString('Remove this invoice?', $html);
        // It names the line, and says the purchase itself survives.
        $this->assertStringContainsString('data-invoice-label="FV-26005188"', $html);
        $this->assertStringContainsString('The purchase itself stays on the month.', $html);

        // The URL travels on the button; the form is aimed at it on opening.
        $this->assertMatchesRegularExpression(
            '#data-invoice-action="[^"]*/entries/'.$entry->id.'/invoice"#',
            $html
        );
    }

    public function test_the_dialog_appears_only_where_something_is_attached(): void
    {
        $this->entry();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->assertDontSee('data-invoice-delete', false);
    }

    public function test_a_line_without_a_number_names_its_date_instead(): void
    {
        $entry = $this->entry(['invoice_number' => 'F-9']);
        $this->upload($entry);
        $entry->update(['invoice_number' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-06')
            ->assertOk()
            ->assertSee('data-invoice-label="10/06/2026"', false);
    }
}
