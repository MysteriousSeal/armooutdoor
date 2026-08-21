<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La ventilation par statut à côté du total, en haut de la liste des commandes.
 *
 * Le total seul ne dit pas où en sont les commandes. Les trois chiffres se
 * lisent contre lui : ils doivent donc couvrir exactement le même périmètre,
 * archives comprises et commandes de test exclues, sinon ils ne se recoupent
 * plus et le lecteur croit à une perte.
 */
class OrderStatusChipsTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status, array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 1000,
            'payment_method' => 'card',
            ...$attributes,
        ]);
    }

    private function kpis(): array
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders')
            ->assertOk()
            ->viewData('kpis');
    }

    public function test_each_status_is_counted_separately(): void
    {
        $this->order('shipped');
        $this->order('shipped');
        $this->order('delivered');
        $this->order('refunded');
        $this->order('placed');

        $kpis = $this->kpis();

        $this->assertSame(2, $kpis['shipped_count']);
        $this->assertSame(1, $kpis['delivered_count']);
        $this->assertSame(1, $kpis['refunded_count']);
    }

    public function test_a_delivered_order_no_longer_counts_as_shipped(): void
    {
        // Les statuts s'excluent : une commande livrée a quitté « expédiée ».
        // Compter les deux ensemble ferait dépasser le total.
        $this->order('delivered');

        $kpis = $this->kpis();

        $this->assertSame(0, $kpis['shipped_count']);
        $this->assertSame(1, $kpis['delivered_count']);
    }

    public function test_archived_orders_are_counted_like_the_total(): void
    {
        $this->order('shipped', ['archived_at' => now()]);
        $this->order('delivered', ['archived_at' => now()]);

        // La vente a eu lieu : l'archivage range la ligne, il ne l'annule pas.
        $kpis = $this->kpis();

        $this->assertSame(1, $kpis['shipped_count']);
        $this->assertSame(1, $kpis['delivered_count']);
        $this->assertSame(2, $kpis['order_count']);
    }

    public function test_test_orders_are_left_out(): void
    {
        $this->order('shipped');
        $this->order('shipped', ['test_marked_at' => now()]);
        $this->order('refunded', ['test_marked_at' => now()]);

        $kpis = $this->kpis();

        $this->assertSame(1, $kpis['shipped_count']);
        $this->assertSame(0, $kpis['refunded_count']);
    }

    public function test_drafts_are_left_out(): void
    {
        $this->order('draft');

        $kpis = $this->kpis();

        $this->assertSame(0, $kpis['shipped_count']);
        $this->assertSame(0, $kpis['order_count']);
    }

    public function test_the_three_counts_never_exceed_the_total(): void
    {
        foreach (['placed', 'preparing', 'shipped', 'delivered', 'refunded'] as $status) {
            $this->order($status);
        }

        $kpis = $this->kpis();

        $sum = $kpis['shipped_count'] + $kpis['delivered_count'] + $kpis['refunded_count'];
        $this->assertLessThanOrEqual($kpis['order_count'], $sum);
        $this->assertSame(3, $sum);
    }

    public function test_the_chips_are_rendered_next_to_the_total(): void
    {
        $this->order('shipped');
        $this->order('delivered');
        $this->order('refunded');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('admin-list-chip--shipped', false)
            ->assertSee('admin-list-chip--delivered', false)
            ->assertSee('admin-list-chip--refunded', false)
            ->assertSee('1 shipped')
            ->assertSee('1 delivered')
            ->assertSee('1 refunded');
    }

    public function test_the_counts_ignore_the_current_tab(): void
    {
        $this->order('shipped');
        $this->order('delivered', ['archived_at' => now()]);

        // Les puces annoncent le portefeuille entier, pas l'onglet ouvert :
        // elles ne doivent pas changer en passant sur les archives.
        $admin = User::factory()->admin()->create();

        foreach (['orders', 'archived', 'draft', 'test'] as $tab) {
            $kpis = $this->actingAs($admin)->get('/admin/orders?tab='.$tab)->assertOk()->viewData('kpis');

            $this->assertSame(1, $kpis['shipped_count'], 'onglet '.$tab);
            $this->assertSame(1, $kpis['delivered_count'], 'onglet '.$tab);
        }
    }

    public function test_the_counts_ignore_the_filters(): void
    {
        $this->order('shipped');
        $this->order('refunded');

        // Même raison : un filtre restreint la table, pas l'en-tête.
        $kpis = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders?status=shipped')
            ->assertOk()
            ->viewData('kpis');

        $this->assertSame(1, $kpis['shipped_count']);
        $this->assertSame(1, $kpis['refunded_count']);
    }

    public function test_the_variant_colours_are_declared_after_the_base_chip(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        // Base et variante ont la même spécificité : c'est l'ordre du fichier
        // qui tranche. Écrite avant, la variante était écrasée en thème clair,
        // et seules les règles [data-theme='dark'] — plus spécifiques —
        // passaient. Les puces n'avaient donc de couleur qu'en thème sombre.
        $base = strpos($css, '.admin-list-chip {');
        $this->assertNotFalse($base);

        foreach (['--shipped', '--delivered', '--refunded', '--muted'] as $variant) {
            $this->assertGreaterThan(
                $base,
                strpos($css, '.admin-list-chip'.$variant.' {'),
                'la variante '.$variant.' doit venir après la règle de base'
            );
        }
    }

    public function test_an_empty_shop_shows_zeros_not_a_crash(): void
    {
        $kpis = $this->kpis();

        $this->assertSame(0, $kpis['shipped_count']);
        $this->assertSame(0, $kpis['delivered_count']);
        $this->assertSame(0, $kpis['refunded_count']);
    }
}
