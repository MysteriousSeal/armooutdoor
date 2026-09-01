<?php

namespace Tests\Feature;

use App\Models\IdentityDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which orders in the list still wait on a proof of age.
 *
 * Two marks with different jobs: one for work waiting on the customer, one
 * for work waiting on the shop. A warning nobody can clear only makes the
 * ones that matter harder to find.
 */
class OrdersListAgeMarkTest extends TestCase
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
            'unit_price_cents' => 1000, 'quantity' => 1, 'line_cents' => 1000,
        ]);

        return $order;
    }

    private function proof(User $user, string $status, ?string $expires = null): void
    {
        IdentityDocument::query()->create([
            'user_id' => $user->id, 'kind' => 'passport', 'original_name' => 'p.pdf',
            'mime' => 'application/pdf', 'size_bytes' => 10, 'path' => null, 'status' => $status,
        ])->forceFill([
            'expires_at' => $expires,
            'reviewed_at' => $status === 'pending' ? null : now(),
        ])->save();
    }

    public function test_a_customer_with_no_proof_is_asked_on_the_restricted_order(): void
    {
        $user = User::factory()->create();
        $this->order($user, true);

        $this->actingAs($user)->get('/orders')
            ->assertOk()
            ->assertSee('order-age-mark--action', false)
            ->assertSee(__('store.orders_age_action'));
    }

    public function test_an_order_without_restricted_items_is_left_alone(): void
    {
        $user = User::factory()->create();
        $this->order($user, false);

        $this->actingAs($user)->get('/orders')->assertOk()->assertDontSee('order-age-mark', false);
    }

    public function test_a_verified_customer_sees_no_mark_at_all(): void
    {
        $user = User::factory()->create();
        $this->proof($user, 'verified', now()->addYear()->toDateString());
        $this->order($user, true);

        $this->actingAs($user)->get('/orders')->assertOk()->assertDontSee('order-age-mark', false);
    }

    public function test_a_document_in_review_gets_the_quiet_mark(): void
    {
        // Nothing for the customer to do, so it does not shout.
        $user = User::factory()->create();
        $this->proof($user, 'pending');
        $this->order($user, true);

        $this->actingAs($user)->get('/orders')
            ->assertOk()
            ->assertSee('order-age-mark--pending', false)
            ->assertDontSee('order-age-mark--action', false);
    }

    public function test_a_lapsed_proof_is_work_for_the_customer_again(): void
    {
        $user = User::factory()->create();
        $this->proof($user, 'verified', now()->subDay()->toDateString());
        $this->order($user, true);

        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('order-age-mark--action', false);
    }

    public function test_a_dispatched_order_is_not_marked(): void
    {
        // The parcel has gone; the badge would ask for something that cannot
        // change anything about it now.
        $user = User::factory()->create();
        $this->order($user, true, 'shipped');

        $this->actingAs($user)->get('/orders')->assertOk()->assertDontSee('order-age-mark', false);
    }

    public function test_only_the_orders_that_need_it_are_marked(): void
    {
        $user = User::factory()->create();
        $this->order($user, true);
        $this->order($user, false);
        $this->order($user, true, 'delivered');

        $html = $this->actingAs($user)->get('/orders')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'order-age-mark--action'));
    }
}
