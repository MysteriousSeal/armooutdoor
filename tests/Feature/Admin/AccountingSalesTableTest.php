<?php

namespace Tests\Feature\Admin;

use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Le tableau des ventes d'un mois. */
class AccountingSalesTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
        $this->travelTo('2026-08-26 10:00:00');
    }

    /** @param array<string, mixed> $overrides */
    private function order(string $placedAt, array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create(['first_name' => 'Camille', 'last_name' => 'Roy'])->id,
            'status' => 'delivered',
            'created_at' => $placedAt,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 10000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 10000, 'payment_method' => 'card',
        ], $overrides));

        // `created_at` n'est pas remplissable : Eloquent le réécrit à
        // maintenant. Une vente de mars doit rester en mars.
        $order->forceFill(['created_at' => $placedAt])->save();

        return $order->refresh();
    }

    private function page(string $month = '2026-03'): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/'.$month)
            ->assertOk();
    }

    public function test_a_sale_shows_its_invoice_client_channel_and_figures(): void
    {
        $order = $this->order('2026-03-12 09:00:00', [
            'marketplace_commission_cents' => 900,
            'payment_fee_cents' => 250,
        ]);

        $this->page()
            ->assertSee('INV-'.$order->number)
            // Le nom s'affiche comme partout dans l'admin : nom de famille
            // en capitales.
            ->assertSee('Camille ROY')
            ->assertSee('Direct')
            ->assertSee('Stock sale')
            ->assertSee('Bank wire')
            ->assertSee('12/03/2026')
            // 100 € encaissés, 11,50 € de frais, 88,50 € perçus.
            ->assertSee('100,00', false)
            ->assertSee('11,50', false)
            ->assertSee('88,50', false)
            // La remarque porte le numéro de commande.
            ->assertSee($order->number);
    }

    public function test_the_marketplace_is_named_when_there_is_one(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'NaturaBuy', 'is_active' => true]);

        $this->order('2026-03-12 09:00:00', [
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'NaturaBuy',
        ]);

        $this->page()->assertSee('NaturaBuy')->assertDontSee('>Direct<', false);
    }

    public function test_own_shipping_is_never_deducted(): void
    {
        // Le port payé de sa poche est une dépense, pas une retenue sur la
        // vente : il ne doit ni baisser le perçu ni entrer dans les frais.
        $this->order('2026-03-12 09:00:00', [
            'payment_fee_cents' => 250,
            'shipping_paid_cents' => 1200,
        ]);

        $this->page()
            ->assertSee('97,50', false)
            ->assertDontSee('85,50', false)
            ->assertDontSee('14,50', false);
    }

    public function test_the_footer_adds_up_the_month(): void
    {
        $this->order('2026-03-02 09:00:00', ['total_cents' => 10000, 'payment_fee_cents' => 250]);
        $this->order('2026-03-20 09:00:00', ['total_cents' => 5000, 'marketplace_commission_cents' => 750]);

        $this->page()
            ->assertSee('2 sales')
            // 150 € au total, 10 € de frais, 140 € perçus.
            ->assertSee('150,00', false)
            ->assertSee('10,00', false)
            ->assertSee('140,00', false);
    }

    public function test_only_the_orders_of_that_month_are_counted(): void
    {
        $inside = $this->order('2026-03-31 23:30:00');
        $before = $this->order('2026-02-28 12:00:00');
        $after = $this->order('2026-04-01 00:30:00');

        $this->page()
            ->assertSee($inside->number)
            ->assertDontSee($before->number)
            ->assertDontSee($after->number);
    }

    public function test_a_refund_is_listed_but_left_out_of_the_totals(): void
    {
        $refunded = $this->order('2026-03-05 09:00:00', ['status' => 'refunded', 'total_cents' => 4000, 'payment_fee_cents' => 100]);
        $this->order('2026-03-06 09:00:00', ['total_cents' => 10000, 'payment_fee_cents' => 250]);

        $response = $this->page()
            ->assertSee($refunded->number)
            // La facture barrée dit déjà tout : pas de pastille en plus. La
            // note sous le tableau garde le mot, elle explique la règle.
            ->assertDontSee('order-chip--refunded', false)
            ->assertSee('is-refunded', false)
            ->assertSee('1 refund left out')
            ->assertSee('1 sale');

        $content = $response->getContent();
        $foot = substr($content, strpos($content, '<tfoot>'));

        // L'argent est reparti : ni les 40 € ni leur euro de frais n'entrent
        // dans le pied. 100 € encaissés, 2,50 € de frais, 97,50 € perçus.
        $this->assertStringContainsString('100,00', $foot);
        $this->assertStringContainsString('2,50', $foot);
        $this->assertStringContainsString('97,50', $foot);
        $this->assertStringNotContainsString('140,00', $foot);
        $this->assertStringNotContainsString('3,50', $foot);
    }

    public function test_drafts_and_test_orders_stay_out(): void
    {
        $draft = $this->order('2026-03-05 09:00:00', ['status' => 'draft']);
        $test = $this->order('2026-03-06 09:00:00');
        $test->forceFill(['test_marked_at' => now()])->save();
        $real = $this->order('2026-03-07 09:00:00');

        $this->page()
            ->assertSee($real->number)
            ->assertDontSee($draft->number)
            ->assertDontSee($test->number)
            ->assertSee('1 sale');
    }

    public function test_a_month_without_a_sale_says_so(): void
    {
        $this->page('2026-01')->assertSee('No sales this month.');
    }

    public function test_the_purchases_month_is_still_empty(): void
    {
        $this->order('2026-03-12 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases/2026-03')
            ->assertOk()
            ->assertSee('Nothing here yet.');
    }

    public function test_the_month_list_counts_the_entries(): void
    {
        $this->order('2026-03-02 09:00:00');
        $this->order('2026-03-20 09:00:00');
        $this->order('2026-05-04 09:00:00');

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#March.*?2 entries#s', $html);
        $this->assertMatchesRegularExpression('#May.*?1 entry#s', $html);
        // Un mois sans écriture le dit plutôt que d'afficher un zéro sec.
        $this->assertMatchesRegularExpression('#January.*?none#s', $html);
    }

    public function test_the_count_ignores_drafts_and_test_orders(): void
    {
        $this->order('2026-03-02 09:00:00');
        $this->order('2026-03-03 09:00:00', ['status' => 'draft']);
        $test = $this->order('2026-03-04 09:00:00');
        $test->forceFill(['test_marked_at' => now()])->save();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#March.*?1 entry#s', $html);
    }

    public function test_a_refund_still_counts_as_an_entry_in_the_list(): void
    {
        // Le compte dit combien de lignes il y a à lire, pas combien
        // d'argent est entré.
        $this->order('2026-03-02 09:00:00', ['status' => 'refunded']);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#March.*?1 entry#s', $html);
    }
}
