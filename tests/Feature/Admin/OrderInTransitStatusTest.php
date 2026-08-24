<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\DashboardPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le statut « en transit » : le colis est parti mais n'est pas arrivé.
 *
 * Il s'intercale entre « expédiée » et « livrée », et l'ordre est
 * obligatoire. Le risque du statut n'est pas la transition elle-même : c'est
 * qu'une commande qui s'y trouve disparaisse silencieusement d'un `whereIn`
 * qui ne l'énumère pas. Plusieurs tests ci-dessous ne gardent que cela.
 */
class OrderInTransitStatusTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'shipped', array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $attributes['user_id'] ?? User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            ...$attributes,
        ]);
    }

    private function metrics(): DashboardMetrics
    {
        return new DashboardMetrics(DashboardPeriod::resolve('30d'));
    }

    public function test_a_shipped_order_can_be_marked_in_transit(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('shipped');

        $this->actingAs($admin)
            ->patch(route('admin.orders.in-transit', $order))
            ->assertRedirect();

        $this->assertSame('in_transit', $order->fresh()->status);
    }

    public function test_the_change_is_written_to_the_status_history(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('shipped');

        $this->actingAs($admin)->patch(route('admin.orders.in-transit', $order));

        $this->assertTrue($order->fresh()->statusHistories->contains('status', 'in_transit'));
        $this->assertTrue(AdminActivityLog::query()->where('action', 'order.in_transit')->exists());
    }

    public function test_a_draft_cannot_be_marked_in_transit(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('draft');

        $this->actingAs($admin)->patch(route('admin.orders.in-transit', $order))->assertNotFound();
        $this->assertSame('draft', $order->fresh()->status);
    }

    /**
     * L'ordre est obligatoire : depuis « expédiée », la seule étape offerte
     * est « en transit », jamais « livrée » directement.
     */
    public function test_a_shipped_order_offers_in_transit_and_not_delivered(): void
    {
        $admin = User::factory()->admin()->create();

        // On vise le bouton d'action lui-même : le texte « Mark as … »
        // apparaît aussi dans la fenêtre de confirmation, et l'assertion
        // passerait alors sans que le bouton existe.
        $this->actingAs($admin)
            ->get('/admin/orders/'.$this->order('shipped')->number)
            ->assertOk()
            ->assertSee('data-modal-open="in-transit-confirm-modal">Mark as in transit', false)
            ->assertDontSee('data-modal-open="deliver-confirm-modal">Mark as delivered', false);
    }

    public function test_an_in_transit_order_offers_delivered(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$this->order('in_transit')->number)
            ->assertOk()
            ->assertSee('data-modal-open="deliver-confirm-modal">Mark as delivered', false)
            ->assertDontSee('data-modal-open="in-transit-confirm-modal">Mark as in transit', false);
    }

    public function test_the_customer_sees_the_french_label_and_note(): void
    {
        $customer = User::factory()->create();
        $order = $this->order('in_transit', ['user_id' => $customer->id]);

        $this->actingAs($customer)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee('En transit')
            ->assertSee('Votre colis voyage vers l’adresse de livraison.', false);
    }

    /**
     * Garde-fou pour `Product::eligibleOrderFor()` : sans `in_transit` dans
     * sa liste, le client perdrait le droit d'écrire son avis pendant tout
     * le trajet du colis, puis le retrouverait à la livraison.
     */
    public function test_an_in_transit_order_can_still_be_reviewed(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $order = $this->order('in_transit', ['user_id' => $customer->id]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => $product->name,
            'image' => $product->image ?? '',
            'unit_price_cents' => 1000,
            'quantity' => 1,
            'line_cents' => 1000,
        ]);

        $this->assertTrue($product->canBeReviewedBy($customer));
    }

    /** Garde-fou pour `BestSellersController` : la vente reste une vente. */
    public function test_an_in_transit_order_still_counts_in_best_sellers(): void
    {
        $product = Product::factory()->create();
        $order = $this->order('in_transit');

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => $product->name,
            'image' => $product->image ?? '',
            'unit_price_cents' => 1000,
            'quantity' => 3,
            'line_cents' => 3000,
        ]);

        $this->get(route('products.best-sellers'))
            ->assertOk()
            ->assertSee($product->slug, false);
    }

    /**
     * Un colis parti sans numéro de suivi reste à traiter, qu'il soit
     * « expédiée » ou déjà « en transit ».
     */
    public function test_an_in_transit_order_without_tracking_raises_the_alert(): void
    {
        $this->order('in_transit', ['tracking_number' => null]);

        $alert = $this->metrics()->attention()
            ->firstWhere('key', 'missing-tracking');

        $this->assertNotNull($alert);
        $this->assertSame(1, $alert['count']);
    }

    public function test_the_admin_list_can_be_filtered_by_in_transit(): void
    {
        $admin = User::factory()->admin()->create();
        $inTransit = $this->order('in_transit');
        $shipped = $this->order('shipped');

        $this->actingAs($admin)
            ->get('/admin/orders?status=in_transit')
            ->assertOk()
            ->assertSee($inTransit->number)
            ->assertDontSee($shipped->number);
    }

    public function test_the_dashboard_pipeline_places_in_transit_between_shipped_and_delivered(): void
    {
        $pipeline = $this->metrics()->pipeline();

        $this->assertSame(
            ['placed', 'preparing', 'shipped', 'in_transit', 'delivered'],
            $pipeline->pluck('status')->all()
        );
        $this->assertSame('In transit', $pipeline->firstWhere('status', 'in_transit')['label']);
    }

    /**
     * Chaque statut visible par le client porte un libellé et une note. Sans
     * note, l'historique affiche une ligne vide — silencieusement.
     */
    public function test_every_customer_facing_status_has_a_label_and_a_note(): void
    {
        foreach (['placed', 'preparing', 'shipped', 'in_transit', 'delivered', 'refunded'] as $status) {
            $this->assertIsString(
                __('store.order_status_'.$status),
                "Missing label for {$status}"
            );
            $this->assertNotSame(
                'store.order_status_note_'.$status,
                __('store.order_status_note_'.$status),
                "Missing store.order_status_note_{$status}"
            );
        }
    }

    /**
     * La valeur stockée porte un underscore : affichée brute, elle donnait
     * « In_transit » dans la pastille, le filtre et l'historique.
     */
    public function test_the_status_label_is_written_without_the_underscore(): void
    {
        $this->assertSame('In transit', Order::labelForStatus('in_transit'));
        $this->assertSame('Shipped', Order::labelForStatus('shipped'));
        $this->assertSame('In transit', $this->order('in_transit')->statusLabel());
    }

    public function test_no_admin_screen_shows_the_raw_underscored_status(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('in_transit');

        foreach (['/admin/orders', '/admin/orders/'.$order->number, '/admin/orders?status=in_transit'] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();

            $response->assertSee('In transit');
            // La valeur brute reste légitime dans les attributs (classes CSS,
            // valeurs de <option>) : on ne traque que le libellé affiché.
            $this->assertStringNotContainsString('>In_transit', $response->getContent(), $url);
            $this->assertStringNotContainsString('· In_transit', $response->getContent(), $url);
        }
    }

    public function test_an_in_transit_order_appears_in_the_pipeline_count(): void
    {
        $this->order('in_transit');

        $stage = $this->metrics()->pipeline()->firstWhere('status', 'in_transit');

        $this->assertSame(1, $stage['count']);
    }
}
