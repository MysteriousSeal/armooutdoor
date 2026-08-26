<?php

namespace Tests\Feature\Admin;

use App\Models\AccountingEntry;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Les écritures saisies à la main dans le journal des ventes. */
class AccountingManualEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
        $this->travelTo('2026-08-26 10:00:00');
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'entered_on' => '2026-03-14',
            'invoice_number' => 'INV-2026-014',
            'client' => 'Club de Nantes',
            'channel' => 'Direct',
            'type' => 'prestation',
            'total' => '240.00',
            'fees' => '12.50',
            'payment_method' => 'bank_wire',
            'remark' => 'Initiation, samedi',
        ], $overrides);
    }

    private function order(string $placedAt, int $totalCents = 10000): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => $totalCents, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => $totalCents, 'payment_method' => 'card',
        ]);

        $order->forceFill(['created_at' => $placedAt])->save();

        return $order->refresh();
    }

    private function submit(array $payload, string $month = '2026-03'): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/accounting/sales/'.$month.'/entries', $payload);
    }

    public function test_an_entry_can_be_added(): void
    {
        $this->submit($this->payload())
            ->assertRedirect('/admin/accounting/sales/2026-03');

        $this->assertDatabaseHas('accounting_entries', [
            'section' => 'sales',
            'invoice_number' => 'INV-2026-014',
            'client' => 'Club de Nantes',
            'type' => 'prestation',
            'total_cents' => 24000,
            'fees_cents' => 1250,
            'payment_method' => 'bank_wire',
        ]);
    }

    public function test_the_entry_shows_in_the_table_with_its_figures(): void
    {
        $this->submit($this->payload());

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->assertSee('INV-2026-014')
            ->assertSee('Club de Nantes')
            ->assertSee('Prestation')
            ->assertSee('Manual')
            ->assertSee('14/03/2026')
            ->assertSee('240,00', false)
            ->assertSee('12,50', false)
            // 240 € moins 12,50 € de frais.
            ->assertSee('227,50', false);
    }

    public function test_an_entry_sits_among_the_orders_at_its_date(): void
    {
        $early = $this->order('2026-03-02 09:00:00');
        $late = $this->order('2026-03-28 09:00:00');
        $this->submit($this->payload(['entered_on' => '2026-03-14']));

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->getContent();

        // Rangée à sa date, pas ajoutée en bout de tableau.
        $this->assertLessThan(strpos($html, 'INV-2026-014'), strpos($html, $early->number));
        $this->assertLessThan(strpos($html, $late->number), strpos($html, 'INV-2026-014'));
    }

    public function test_an_entry_counts_in_the_totals(): void
    {
        $this->order('2026-03-02 09:00:00', 10000);
        $this->submit($this->payload(['total' => '240.00', 'fees' => '12.50']));

        $content = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->getContent();

        $foot = substr($content, strpos($content, '<tfoot>'));

        // 100 € + 240 € = 340 €, 12,50 € de frais, 327,50 € perçus.
        $this->assertStringContainsString('340,00', $foot);
        $this->assertStringContainsString('327,50', $foot);
        $this->assertStringContainsString('2 sales', $foot);
    }

    public function test_a_date_outside_the_month_is_refused(): void
    {
        // Enregistrée, elle disparaîtrait de la page qui vient de l'accepter.
        $this->submit($this->payload(['entered_on' => '2026-04-02']))
            ->assertSessionHasErrors('entered_on');

        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_the_essentials_are_required(): void
    {
        $this->submit(['type' => 'stock_sale'])
            ->assertSessionHasErrors(['entered_on', 'total', 'payment_method']);
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $this->submit($this->payload(['type' => 'troc']))->assertSessionHasErrors('type');
    }

    public function test_an_entry_can_be_corrected(): void
    {
        $this->submit($this->payload());
        $entry = AccountingEntry::query()->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/accounting/sales/2026-03/entries/'.$entry->id, $this->payload([
                'client' => 'Club de Rennes',
                'total' => '300.00',
            ]))
            ->assertRedirect('/admin/accounting/sales/2026-03');

        $entry->refresh();
        $this->assertSame('Club de Rennes', $entry->client);
        $this->assertSame(30000, $entry->total_cents);
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $this->submit($this->payload());
        $entry = AccountingEntry::query()->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->delete('/admin/accounting/sales/2026-03/entries/'.$entry->id)
            ->assertRedirect('/admin/accounting/sales/2026-03');

        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_the_author_is_recorded_and_kept_through_a_correction(): void
    {
        $author = User::factory()->admin()->create();

        $this->actingAs($author)->post('/admin/accounting/sales/2026-03/entries', $this->payload());
        $entry = AccountingEntry::query()->firstOrFail();
        $this->assertSame($author->id, $entry->created_by_user_id);

        // Corriger une écriture ne fait pas de vous son auteur.
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/accounting/sales/2026-03/entries/'.$entry->id, $this->payload(['client' => 'Autre']));

        $this->assertSame($author->id, $entry->refresh()->created_by_user_id);
    }

    public function test_a_staff_admin_cannot_write_in_the_journal(): void
    {
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->post('/admin/accounting/sales/2026-03/entries', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_a_purchases_entry_never_shows_among_the_sales(): void
    {
        AccountingEntry::query()->create([
            'section' => 'purchases',
            'entered_on' => '2026-03-14',
            'invoice_number' => 'ACH-2026-001',
            'type' => 'other',
            'total_cents' => 5000,
            'fees_cents' => 0,
            'payment_method' => 'bank_wire',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->assertDontSee('ACH-2026-001');
    }

    public function test_an_entry_counts_in_the_month_list(): void
    {
        $this->order('2026-03-02 09:00:00');
        $this->submit($this->payload(['entered_on' => '2026-03-14']));

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        // Une commande et une écriture : la liste annonce deux lignes à lire.
        $this->assertMatchesRegularExpression('#March.*?2 entries#s', $html);
    }

    public function test_a_month_holding_only_entries_is_not_shown_as_empty(): void
    {
        $this->submit($this->payload(['entered_on' => '2026-03-14']));

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        // Une carte ne porte qu'un compte : dire « 1 entry » suffit à dire
        // qu'elle ne dit pas « none ».
        $this->assertMatchesRegularExpression('#March.*?1 entry#s', $html);
    }

    public function test_a_purchases_entry_never_swells_the_sales_count(): void
    {
        AccountingEntry::query()->create([
            'section' => 'purchases',
            'entered_on' => '2026-03-14',
            'type' => 'other',
            'total_cents' => 5000,
            'fees_cents' => 0,
            'payment_method' => 'bank_wire',
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#March.*?none#s', $html);
    }
}
