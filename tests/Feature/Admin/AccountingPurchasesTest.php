<?php

namespace Tests\Feature\Admin;

use App\Models\AccountingEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** A month of purchases: what the shop paid out. */
class AccountingPurchasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-26 10:00:00');
    }

    /**
     * A complete, valid purchase. Overrides replace single fields.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'entered_on' => '2026-07-14',
            'invoice_number' => 'F-2026-0142',
            'client' => 'DM Diffusion',
            'type' => 'achat stock',
            'total' => '120.00',
            'vat_rate' => '20',
            'payment_method' => 'bank_wire',
            'remark' => 'Réassort gants',
        ], $overrides);
    }

    /** Posts a purchase into a month, as the owner. */
    private function submit(array $payload, string $month = '2026-07'): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/accounting/purchases/'.$month.'/entries', $payload);
    }

    /** Opens a month of purchases as the owner. */
    private function page(string $month = '2026-07'): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/'.$month)
            ->assertOk();
    }

    public function test_a_purchase_can_be_recorded(): void
    {
        $this->submit($this->payload())->assertRedirect('/admin/accounting/purchases/2026-07');

        $this->assertDatabaseHas('accounting_entries', [
            'section' => 'purchases',
            'invoice_number' => 'F-2026-0142',
            'client' => 'DM Diffusion',
            'type' => 'achat stock',
            'total_cents' => 12000,
            'vat_rate_basis_points' => 2000,
        ]);
    }

    public function test_the_line_shows_every_field_asked_for(): void
    {
        $this->submit($this->payload());

        $this->page()
            ->assertSee('14/07/2026')
            ->assertSee('F-2026-0142')
            ->assertSee('DM Diffusion')
            ->assertSee('achat stock')
            ->assertSee('Bank wire')
            // 120 € incl. VAT at 20% is 100 € before tax and 20 € of tax.
            ->assertSee('100,00', false)
            ->assertSee('20,00', false)
            ->assertSee('120,00', false)
            ->assertSee('20%');
    }

    public function test_the_three_amounts_are_worked_back_from_the_invoice(): void
    {
        $entry = AccountingEntry::query()->create([
            'section' => 'purchases',
            'entered_on' => '2026-07-14',
            'type' => 'achat stock',
            'total_cents' => 12000,
            'vat_rate_basis_points' => 2000,
            'fees_cents' => 0,
            'payment_method' => 'bank_wire',
        ]);

        $this->assertSame(10000, $entry->exVatCents());
        $this->assertSame(2000, $entry->vatCents());
        // The three always add up, whatever the rounding.
        $this->assertSame($entry->total_cents, $entry->exVatCents() + $entry->vatCents());
    }

    public function test_an_odd_rate_still_adds_up_to_the_invoice(): void
    {
        $entry = AccountingEntry::query()->create([
            'section' => 'purchases',
            'entered_on' => '2026-07-14',
            'type' => 'achat fournitures',
            'total_cents' => 9999,
            'vat_rate_basis_points' => 550,
            'fees_cents' => 0,
            'payment_method' => 'card',
        ]);

        $this->assertSame($entry->total_cents, $entry->exVatCents() + $entry->vatCents());
    }

    public function test_the_month_adds_up_its_three_columns(): void
    {
        $this->submit($this->payload(['total' => '120.00', 'vat_rate' => '20']));
        $this->submit($this->payload(['entered_on' => '2026-07-20', 'total' => '60.00', 'vat_rate' => '20']));

        $content = $this->page()->getContent();
        $foot = substr($content, strpos($content, '<tfoot>'));

        // 180 € paid, 150 € before tax, 30 € of tax.
        $this->assertStringContainsString('150,00', $foot);
        $this->assertStringContainsString('30,00', $foot);
        $this->assertStringContainsString('180,00', $foot);
        $this->assertStringContainsString('2 purchases', $foot);
    }

    public function test_the_type_is_free_text(): void
    {
        // Not one of the sales kinds, and accepted all the same.
        $this->submit($this->payload(['type' => 'frais de port sur retour']))
            ->assertRedirect('/admin/accounting/purchases/2026-07');

        $this->assertDatabaseHas('accounting_entries', ['type' => 'frais de port sur retour']);
    }

    public function test_the_type_field_is_a_plain_text_box(): void
    {
        $this->submit($this->payload(['type' => 'achat fournitures']));

        // No list of past values hanging off it: a purchase is whatever its
        // invoice is for, and the field says so plainly.
        $this->page()
            ->assertDontSee('entry-type-options', false)
            ->assertDontSee('<datalist', false)
            ->assertSee('name="type"', false);
    }

    public function test_a_rate_is_required_on_a_purchase(): void
    {
        $this->submit($this->payload(['vat_rate' => null]))->assertSessionHasErrors('vat_rate');
    }

    public function test_a_sale_refuses_a_rate(): void
    {
        // Sales settle their VAT elsewhere; accepting one here would record a
        // figure nothing reads.
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/accounting/sales/2026-07/entries', [
                'entered_on' => '2026-07-14',
                'type' => 'prestation',
                'total' => '240.00',
                'vat_rate' => '20',
                'payment_method' => 'bank_wire',
            ])
            ->assertSessionHasErrors('vat_rate');
    }

    public function test_a_sale_still_picks_from_its_short_list(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/accounting/sales/2026-07/entries', [
                'entered_on' => '2026-07-14',
                'type' => 'achat stock',
                'total' => '240.00',
                'payment_method' => 'bank_wire',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_a_purchase_can_be_corrected_and_deleted(): void
    {
        $this->submit($this->payload());
        $entry = AccountingEntry::query()->firstOrFail();
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->put('/admin/accounting/purchases/2026-07/entries/'.$entry->id, $this->payload([
                'client' => 'Cybergun',
                'total' => '240.00',
            ]))
            ->assertRedirect('/admin/accounting/purchases/2026-07');

        $entry->refresh();
        $this->assertSame('Cybergun', $entry->client);
        $this->assertSame(24000, $entry->total_cents);

        $this->actingAs($owner)
            ->delete('/admin/accounting/purchases/2026-07/entries/'.$entry->id)
            ->assertRedirect('/admin/accounting/purchases/2026-07');

        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_the_month_list_counts_purchases(): void
    {
        $this->submit($this->payload());

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#July.*?1 entry#s', $html);
    }

    public function test_a_staff_admin_reaches_none_of_it(): void
    {
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/accounting/purchases/2026-07')
            ->assertForbidden();
    }

    public function test_the_two_buttons_sit_where_they_do_on_a_month_of_sales(): void
    {
        $content = $this->page()->getContent();

        // In the page's own hero, not the admin bar above it: the layout has a
        // header of its own, and slicing at the first one would miss this.
        $start = strpos($content, 'admin-list-hero');
        $hero = substr($content, $start, strpos($content, '</header>', $start) - $start);

        $this->assertStringContainsString('accounting-hero-buttons', $hero);
        $this->assertLessThan(
            strpos($hero, 'Add entry'),
            strpos($hero, 'Download PDF'),
        );
    }

    public function test_the_pdf_button_is_switched_off_until_it_does_something(): void
    {
        $this->submit($this->payload());

        $this->page()
            ->assertSee('Not available yet')
            ->assertSee('is-disabled', false)
            // Nothing to click through to yet.
            ->assertDontSee('/admin/accounting/purchases/2026-07/pdf', false);
    }
}
