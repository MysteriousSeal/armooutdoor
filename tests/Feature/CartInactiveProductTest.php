<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A cart can still hold a product that was taken off sale after it was added.
 *
 * The cart already drops that line from what it shows and bills, so it must
 * not count anywhere else either: not in the header badge, not as a reason to
 * open checkout, and never as an order that charges shipping for nothing.
 */
class CartInactiveProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
    }

    private function product(): Product
    {
        return Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function orderForm(User $user, string $paymentMethod): array
    {
        return [
            'address_id' => Address::factory()->for($user)->create()->id,
            'same_billing_address' => true,
            'carrier_id' => Carrier::query()->where('slug', 'colissimo-home')->firstOrFail()->id,
            'payment_method' => $paymentMethod,
        ];
    }

    private function assertHeaderBadgeHidden(string $html): void
    {
        $this->assertMatchesRegularExpression('/class="cart-badge"\s+hidden/', $html);
    }

    public function test_a_guest_cart_stops_counting_a_product_taken_off_sale(): void
    {
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertSessionHas('status');

        $product->update(['is_active' => false]);

        $this->assertHeaderBadgeHidden($this->get('/cart')->assertOk()->getContent());
        $this->assertTrue(app(Cart::class)->isEmpty());
        $this->assertSame(0, app(Cart::class)->quantity());
    }

    public function test_a_guest_who_logs_in_cannot_check_out_a_product_taken_off_sale(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $product->update(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);

        // Logging in does not carry the retired product into the account cart.
        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);

        $this->get('/checkout')->assertRedirect('/cart');

        foreach (['paypal', 'card'] as $paymentMethod) {
            // Sent back to the empty cart before any payment is started,
            // Stripe included, and no order that bills shipping alone.
            $this->post('/checkout', $this->orderForm($user, $paymentMethod))->assertRedirect('/cart');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_an_account_cart_row_left_behind_is_not_an_order(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        // A bulk update skips the model event that would clear the cart rows.
        Product::query()->whereKey($product->id)->update(['is_active' => false]);
        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id]);

        $this->assertHeaderBadgeHidden($this->actingAs($user)->get('/cart')->assertOk()->getContent());
        $this->actingAs($user)->get('/checkout')->assertRedirect('/cart');

        foreach (['paypal', 'card'] as $paymentMethod) {
            $this->actingAs($user)->post('/checkout', $this->orderForm($user, $paymentMethod))->assertRedirect('/cart');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_mixed_cart_bills_only_what_is_still_on_sale(): void
    {
        $user = User::factory()->create();
        $onSale = $this->product();
        $retired = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $onSale->id, 'quantity' => 2]);
        $this->actingAs($user)->post('/cart', ['product_id' => $retired->id, 'quantity' => 3]);
        Product::query()->whereKey($retired->id)->update(['is_active' => false]);

        // The badge counts the two that can still be bought, not five.
        $this->assertMatchesRegularExpression('/class="cart-badge"\s*>\s*2\s*</', $this->actingAs($user)->get('/cart')->getContent());

        $this->actingAs($user)->post('/checkout', $this->orderForm($user, 'paypal'));

        $order = Order::query()->firstOrFail();
        $this->assertSame(1, $order->items()->count());
        $this->assertSame($onSale->id, $order->items()->value('product_id'));
        $this->assertSame($onSale->price_cents * 2, $order->subtotal_cents);
    }

    public function test_the_cart_cannot_be_updated_with_a_product_taken_off_sale(): void
    {
        $product = $this->product();
        $product->update(['is_active' => false]);

        $this->patch('/cart/'.$product->slug, ['quantity' => 1])->assertNotFound();
        $this->patchJson('/cart/'.$product->slug, ['quantity' => 1])->assertNotFound();

        $user = User::factory()->create();
        $this->actingAs($user)->patch('/cart/'.$product->slug, ['quantity' => 1])->assertNotFound();

        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);
    }

    public function test_a_product_on_sale_is_still_ordered_as_before(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($user)->get('/checkout')->assertOk();

        $response = $this->actingAs($user)->post('/checkout', $this->orderForm($user, 'paypal'));

        $order = Order::query()->firstOrFail();
        $response->assertRedirect('/orders/'.$order->number);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame($product->price_cents * 2, $order->subtotal_cents);
        $this->assertSame($order->subtotal_cents + $order->shipping_cents, $order->total_cents);
    }
}
