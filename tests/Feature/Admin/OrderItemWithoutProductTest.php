<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une ligne de commande sans produit au catalogue.
 *
 * Les ventes rapatriées d'une place de marché portent parfois un article que
 * la boutique ne référence pas. La vente a eu lieu : la ligne doit exister,
 * avec son libellé et son prix, sans que rien ne soit créé au catalogue. Tout
 * ce qui suppose un produit derrière la ligne — vignette, lien, avis — doit
 * tenir sans lui.
 */
class OrderItemWithoutProductTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithUnlinkedItem(): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create(['external' => true, 'email' => null])->id,
            'status' => 'shipped',
            'address_snapshot' => ['first_name' => 'Geoffrey', 'last_name' => 'Alvarez', 'line1' => 'Consigne', 'postal_code' => '59620', 'city' => 'Aulnoye-Aymeries', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'Geoffrey', 'last_name' => 'Alvarez', 'line1' => '19 Rue Queue Noire Jean', 'postal_code' => '59440', 'city' => 'Saint-Hilaire-sur-Helpe', 'country' => 'FR'],
            'carrier_method' => 'relay',
            'carrier_snapshot' => ['name' => ['fr' => 'Chronopost Shop2Shop']],
            'subtotal_cents' => 699,
            'shipping_cents' => 350,
            'discount_cents' => 0,
            'total_cents' => 1049,
            'payment_method' => 'card',
            'is_manual' => true,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_slug' => '',
            'name' => ['en' => 'Plombs diabolo 4.5mm', 'fr' => 'Plombs diabolo 4.5mm'],
            'image' => '',
            'unit_price_cents' => 699,
            'quantity' => 1,
            'line_cents' => 699,
        ]);

        return $order;
    }

    public function test_the_admin_order_page_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->orderWithUnlinkedItem();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('Plombs diabolo 4.5mm');
    }

    public function test_the_orders_list_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->orderWithUnlinkedItem();

        $this->actingAs($admin)->get('/admin/orders')->assertOk()->assertSee($order->number);
    }

    public function test_the_invoice_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->orderWithUnlinkedItem();

        // La facture est le document qui part au client : c'est là qu'un
        // produit manquant coûterait le plus cher.
        $this->actingAs($admin)->get(route('admin.orders.invoice', $order))->assertOk();
    }

    public function test_the_customer_sees_the_line_on_their_order(): void
    {
        $order = $this->orderWithUnlinkedItem();

        $this->actingAs($order->user)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee('Plombs diabolo 4.5mm');
    }

    public function test_the_dashboard_renders_with_an_unlinked_best_seller(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orderWithUnlinkedItem();

        // Les meilleures ventes affichent une vignette et un lien produit :
        // sans produit, la tuile doit rester vide plutôt que tomber.
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_the_csv_export_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orderWithUnlinkedItem();

        $this->actingAs($admin)->get(route('admin.orders.export'))->assertOk();
    }

    public function test_nothing_was_added_to_the_catalogue(): void
    {
        $this->orderWithUnlinkedItem();

        $this->assertSame(0, Product::query()->count());
    }
}
