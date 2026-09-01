<?php

namespace Tests\Feature;

use App\Models\IdentityDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The same notice on a placed order, in the tense of an order.
 *
 * It is no longer « vous pouvez commander » — the order exists. What is left
 * is the dispatch, and the notice says what is wanted before it happens.
 */
class OrderAgeNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user, bool $restricted, string $status = 'placed'): Order
    {
        $product = Product::factory()->create(['is_active' => true, 'age_restricted' => $restricted]);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0, 'total_cents' => 1000,
            'payment_method' => 'card',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => $product->localizedName(),
            'image' => '',
            'unit_price_cents' => 1000,
            'quantity' => 1,
            'line_cents' => 1000,
        ]);

        return $order->fresh('items');
    }

    public function test_an_ordinary_order_says_nothing_about_age(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, false);

        $this->actingAs($user)->get('/orders/'.$order->number)->assertOk()->assertDontSee('data-cart-age', false);
    }

    public function test_an_order_awaiting_dispatch_asks_for_the_proof(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, true);

        $this->actingAs($user)->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee('data-cart-age', false)
            ->assertSee('Un article de votre commande est réservé aux majeurs')
            ->assertSee(__('store.order_age_none'));
    }

    public function test_a_shipped_order_stops_asking(): void
    {
        // The parcel has gone; asking now is telling somebody about a door
        // that already closed.
        $user = User::factory()->create();
        $order = $this->order($user, true, 'shipped');

        $this->actingAs($user)->get('/orders/'.$order->number)->assertOk()->assertDontSee('data-cart-age', false);
    }

    public function test_a_verified_customer_is_simply_reassured(): void
    {
        $user = User::factory()->create();
        IdentityDocument::query()->create([
            'user_id' => $user->id, 'kind' => 'passport', 'original_name' => 'p.pdf',
            'mime' => 'application/pdf', 'size_bytes' => 10, 'path' => null, 'status' => 'verified',
        ])->forceFill(['expires_at' => now()->addYear(), 'reviewed_at' => now()])->save();

        $order = $this->order($user, true);

        $this->actingAs($user)->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee('cart-age--verified', false)
            ->assertDontSee('cart-age-cta', false);
    }

    public function test_the_wording_belongs_to_an_order_and_not_to_a_basket(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, true);

        $this->actingAs($user)->get('/orders/'.$order->number)
            ->assertOk()
            ->assertDontSee(__('store.cart_age_none'))
            ->assertDontSee('de votre panier');
    }
}
