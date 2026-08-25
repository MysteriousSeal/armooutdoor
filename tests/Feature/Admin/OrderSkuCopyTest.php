<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le SKU d'une ligne de commande se copie d'un clic. */
class OrderSkuCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    private function orderWithItem(?Product $product, ?string $sku = null): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 1000, 'payment_method' => 'card',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'product_slug' => $product?->slug ?? 'gone',
            'name' => $product?->name ?? ['fr' => 'Article'],
            'image' => '',
            'sku' => $sku,
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'line_cents' => 1000,
        ]);

        return $order;
    }

    public function test_a_sku_renders_as_a_copy_button(): void
    {
        $product = Product::factory()->create(['sku' => 'MOLLE-BELT-EVA-KHAKI']);
        $order = $this->orderWithItem($product);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('data-copy-code="MOLLE-BELT-EVA-KHAKI"', false)
            ->assertSee('order-item-sku-copy', false)
            // Un vrai bouton : il répond au clavier sans rien ajouter.
            ->assertSee('<button', false);
    }

    public function test_the_sku_is_announced_to_assistive_tech(): void
    {
        $product = Product::factory()->create(['sku' => 'CAG-URBBLK-BREATH']);
        $order = $this->orderWithItem($product);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('aria-label="Copy SKU CAG-URBBLK-BREATH"', false);
    }

    /** Sans SKU, pas de bouton à cliquer. */
    public function test_a_line_without_a_sku_has_no_copy_button(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        $order = $this->orderWithItem($product);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertDontSee('order-item-sku-copy', false);
    }

    public function test_the_order_number_is_a_copy_button(): void
    {
        $order = $this->orderWithItem(Product::factory()->create(['sku' => 'ANY']));

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('data-copy-code="'.$order->number.'"', false)
            ->assertSee('admin-title-copy', false)
            ->assertSee('aria-label="Copy order number '.$order->number.'"', false);
    }

    /**
     * Le titre reste un titre. Avec `role="button"` sur le h2, la page perdait
     * son en-tête pour les lecteurs d'écran.
     */
    public function test_the_heading_is_still_a_heading(): void
    {
        $order = $this->orderWithItem(Product::factory()->create(['sku' => 'ANY']));

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/'.$order->number)->assertOk()->getContent();

        $this->assertStringContainsString('<h2 class="admin-list-title">', $html);
        $this->assertStringNotContainsString('role="button"', $html);
    }

    public function test_the_copy_script_is_loaded_on_the_page(): void
    {
        $order = $this->orderWithItem(Product::factory()->create(['sku' => 'ANY-SKU']));

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('js/admin-copy-code.js', false);
    }
}
