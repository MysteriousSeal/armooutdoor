<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\AnalyticsItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's events, in the vocabulary Google reserves for shopping.
 *
 * cart_item_added and order_placed read better and mean nothing to GA4, which
 * files anything outside its own names as a counter and leaves it out of every
 * report that makes the tool worth having. PostHog keeps the shop's names; the
 * translation happens on the way out.
 */
class EcommerceEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_is_described_the_way_google_expects(): void
    {
        $product = Product::factory()->create(['sku' => 'ARM-1', 'brand' => 'Umarex', 'price_cents' => 1490]);

        $item = AnalyticsItems::forProduct($product, 3);

        $this->assertSame('ARM-1', $item['item_id']);
        $this->assertSame('Umarex', $item['item_brand']);
        $this->assertSame(14.90, $item['price']);
        $this->assertSame(3, $item['quantity']);
    }

    public function test_a_product_without_a_sku_falls_back_to_its_id(): void
    {
        $product = Product::factory()->create(['sku' => null]);

        $this->assertSame((string) $product->id, AnalyticsItems::forProduct($product)['item_id']);
    }

    public function test_an_order_is_read_from_its_own_rows(): void
    {
        // Not from the products they point at: renaming or repricing a product
        // after a sale must not change what the sale is reported to have been.
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1980,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 1980,
            'payment_method' => 'card',
        ]);
        $order->items()->create([
            'product_id' => Product::factory()->create()->id,
            'product_slug' => 'nom-au-moment-de-la-vente',
            'image' => '',
            'name' => 'Nom au moment de la vente',
            'unit_price_cents' => 990,
            'quantity' => 2,
            'line_cents' => 1980,
        ]);

        $items = AnalyticsItems::forOrder($order->fresh('items'));

        $this->assertCount(1, $items);
        $this->assertSame('Nom au moment de la vente', $items[0]['item_name']);
        $this->assertSame(9.90, $items[0]['price']);
        $this->assertSame(2, $items[0]['quantity']);
    }

    public function test_the_product_page_carries_the_item_on_its_form(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('data-analytics-item="', false)
            ->assertSee('item_name', false);
    }

    public function test_the_loader_sends_google_its_own_names(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        $this->assertStringContainsString("name: 'add_to_cart'", $js);
        $this->assertStringContainsString("window.gtag('event', google.name", $js);
        // PostHog keeps the shop's own vocabulary.
        $this->assertStringContainsString("capture(\n                'cart_item_added',", $js);
    }

    public function test_the_checkout_and_order_pages_name_the_reserved_events(): void
    {
        $this->assertStringContainsString(
            "'name' => 'begin_checkout'",
            file_get_contents(resource_path('views/checkout/show.blade.php')),
        );

        $order = file_get_contents(resource_path('views/orders/show.blade.php'));

        $this->assertStringContainsString("'name' => 'purchase'", $order);
        // Without it, a reload of the confirmation page books the sale twice.
        $this->assertStringContainsString("'transaction_id' => \$order->number", $order);
    }
}
