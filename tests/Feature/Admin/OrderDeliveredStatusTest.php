<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le statut « livrée » : la dernière étape utile d'une commande, entre
 * l'expédition et un éventuel remboursement.
 *
 * Une commande livrée reste une vente : elle compte dans le chiffre, dans les
 * meilleures ventes, et son destinataire peut toujours laisser un avis.
 */
class OrderDeliveredStatusTest extends TestCase
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

    public function test_a_shipped_order_can_be_marked_delivered(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('shipped');

        $this->actingAs($admin)
            ->patch(route('admin.orders.deliver', $order))
            ->assertRedirect();

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_the_change_is_written_to_the_status_history(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('shipped');

        $this->actingAs($admin)->patch(route('admin.orders.deliver', $order));

        $this->assertTrue($order->fresh()->statusHistories->contains('status', 'delivered'));
    }

    public function test_the_change_is_recorded_in_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('shipped');

        $this->actingAs($admin)->patch(route('admin.orders.deliver', $order));

        $this->assertTrue(AdminActivityLog::query()->where('action', 'order.delivered')->exists());
    }

    public function test_a_draft_cannot_be_delivered(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('draft');

        $this->actingAs($admin)->patch(route('admin.orders.deliver', $order))->assertNotFound();
        $this->assertSame('draft', $order->fresh()->status);
    }

    /**
     * « Livrée » s'atteint depuis « en transit », pas depuis « expédiée » :
     * l'étape intermédiaire est obligatoire, et le bouton l'impose.
     */
    public function test_the_button_appears_only_on_an_in_transit_order(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$this->order('in_transit')->number)
            ->assertOk()
            ->assertSee('Mark as delivered');

        foreach (['placed', 'preparing', 'shipped', 'delivered'] as $status) {
            $this->actingAs($admin)
                ->get('/admin/orders/'.$this->order($status)->number)
                ->assertOk()
                ->assertDontSee('Mark as delivered');
        }
    }

    public function test_a_delivered_order_can_still_be_refunded(): void
    {
        $owner = User::factory()->admin()->create();
        $order = $this->order('delivered');

        // Les retours arrivent après la livraison : c'est justement là que les
        // remboursements se font.
        $this->actingAs($owner)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('Mark as refunded');

        $this->actingAs($owner)->patch(route('admin.orders.refund', $order))->assertRedirect();
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_a_delivered_order_still_counts_as_revenue(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('delivered');

        $dashboard = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $this->assertSame(1500, $dashboard->viewData('headline')['revenue_cents']);
    }

    public function test_a_delivered_order_is_no_longer_chased_for_tracking(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('delivered', ['tracking_number' => null]);

        // Un colis arrivé n'a plus besoin qu'on lui réclame un suivi.
        $orders = $this->actingAs($admin)->get('/admin/orders')->assertOk();
        $this->assertSame(0, $orders->viewData('kpis')['missing_tracking_count']);
        $this->assertSame(0, $orders->viewData('kpis')['to_prepare_count']);
    }

    public function test_the_status_filter_accepts_delivered(): void
    {
        $admin = User::factory()->admin()->create();
        $delivered = $this->order('delivered');
        $shipped = $this->order('shipped');

        $this->actingAs($admin)
            ->get('/admin/orders?status=delivered')
            ->assertOk()
            ->assertSee($delivered->number)
            ->assertDontSee($shipped->number);
    }

    public function test_the_address_can_no_longer_be_edited(): void
    {
        // L'allowlist existante ne laisse passer que placed et preparing.
        $this->assertFalse($this->order('delivered')->addressIsEditable());
    }

    public function test_the_customer_sees_the_status_in_french(): void
    {
        $customer = User::factory()->create();
        $order = $this->order('delivered', ['user_id' => $customer->id]);

        $this->actingAs($customer)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee(__('store.order_status_delivered'))
            ->assertSee(__('store.order_thanks_delivered'));
    }

    public function test_a_delivered_order_still_allows_a_review(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $order = $this->order('delivered', ['user_id' => $customer->id]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => ['fr' => 'X'],
            'image' => '',
            'unit_price_cents' => 1000,
            'quantity' => 1,
            'line_cents' => 1000,
        ]);

        // Le droit à l'avis ne tenait qu'au statut « expédiée » : passer à
        // « livrée » l'aurait supprimé au moment précis où le client a le
        // produit en main.
        $this->assertNotNull($product->eligibleOrderFor($customer));
    }

    public function test_a_delivered_order_still_counts_towards_best_sellers(): void
    {
        $product = Product::factory()->create();
        $order = $this->order('delivered');

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => ['fr' => 'X'],
            'image' => '',
            'unit_price_cents' => 1000,
            'quantity' => 3,
            'line_cents' => 3000,
        ]);

        $this->get('/meilleures-ventes')->assertOk()->assertSee($product->slug, false);
    }

    public function test_the_chip_uses_its_own_colour_class(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('delivered');

        // La classe porte la couleur : sans elle le statut tomberait sur le
        // style par défaut et se confondrait avec un autre.
        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('order-chip--delivered', false);

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('badge-delivered', false);
    }
}
