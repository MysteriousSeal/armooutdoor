<?php

namespace Tests\Feature\Admin;

use App\Models\IdentityDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The « -18 » chip on the order list, and where each order stands under it.
 *
 * Unlike the customer's own list, this one keeps the chip after dispatch: the
 * back office is the record of what went out verified.
 */
class AdminOrdersListAgeChipTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer, bool $restricted, string $status = 'placed'): Order
    {
        $product = Product::factory()->create(['is_active' => true, 'age_restricted' => $restricted]);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
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
            // The checkout stores the translated array, not a string: a fixture
            // that stores a string hides every bug in how the name is read.
            'name' => $product->name,
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

    private function list(): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'owner', 'is_admin' => true]))
            ->get('/admin/orders')
            ->assertOk();
    }

    public function test_an_ordinary_order_carries_no_chip(): void
    {
        $this->order(User::factory()->create(), false);

        $this->list()->assertDontSee('order-chip--age', false);
    }

    public function test_a_customer_with_no_proof_reads_missing(): void
    {
        $this->order(User::factory()->create(), true);

        $this->list()->assertSee('order-chip--age-none', false)->assertSee('Missing', false);
    }

    public function test_a_verified_customer_reads_verified(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->addYear()->toDateString());
        $this->order($customer, true);

        $this->list()->assertSee('order-chip--age-verified', false)->assertSee('Verified', false);
    }

    public function test_a_lapsed_proof_reads_expired(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->subDay()->toDateString());
        $this->order($customer, true);

        $this->list()->assertSee('order-chip--age-expired', false)->assertSee('Expired', false);
    }

    public function test_the_chip_survives_dispatch(): void
    {
        $this->order(User::factory()->create(), true, 'delivered');

        $this->list()->assertSee('order-chip--age', false);
    }

    public function test_the_mark_is_there_for_the_eye_and_hidden_from_the_reader(): void
    {
        // The word carries the meaning; the -18 is the rule it falls under.
        $this->order(User::factory()->create(), true);

        $this->list()->assertSee('<span class="order-chip-age-mark" aria-hidden="true">-18</span>', false);
    }

    public function test_twenty_orders_do_not_cost_twenty_lookups(): void
    {
        // The customers' documents ride along with the orders, and the
        // restricted ones are found in a single query for the page.
        foreach (range(1, 12) as $i) {
            $customer = User::factory()->create();
            $this->proof($customer, 'verified', now()->addYear()->toDateString());
            $this->order($customer, true);
        }

        $admin = User::factory()->create(['role' => 'owner', 'is_admin' => true]);

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/orders')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(35, $queries, "Expected a fixed number of queries, ran {$queries}");
    }
}
