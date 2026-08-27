<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one search box in the top bar.
 *
 * It is what an admin reaches for with a customer on the phone, so it has to
 * find an order from any of the things that customer might read out: the
 * order number, their email, their name. What it must not do is answer with
 * rows that belong somewhere else — an archived order, another admin's
 * account — or run at all on an empty box.
 */
class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(User $customer, array $overrides = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            ...$overrides,
        ]);
    }

    private function search(string $term)
    {
        return $this->actingAs($this->admin())
            ->get('/admin/search?q='.urlencode($term))
            ->assertOk();
    }

    public function test_an_empty_box_searches_nothing_at_all(): void
    {
        $this->order(User::factory()->create());

        $response = $this->actingAs($this->admin())->get('/admin/search')->assertOk();

        $this->assertCount(0, $response->viewData('orders'));
        $this->assertCount(0, $response->viewData('customers'));
        $this->assertCount(0, $response->viewData('products'));
        $response->assertSee('Type something above to search.');
    }

    public function test_whitespace_alone_is_an_empty_box(): void
    {
        $this->order(User::factory()->create());

        $this->search('   ')->assertSee('Type something above to search.');
    }

    public function test_an_order_is_found_by_its_number(): void
    {
        $order = $this->order(User::factory()->create());

        $found = $this->search($order->number)->viewData('orders');

        $this->assertSame([$order->id], $found->pluck('id')->all());
    }

    public function test_an_order_is_found_by_the_email_of_the_customer_who_placed_it(): void
    {
        $customer = User::factory()->create(['email' => 'julien@example.com']);
        $order = $this->order($customer);

        $found = $this->search('julien@example.com')->viewData('orders');

        $this->assertSame([$order->id], $found->pluck('id')->all());
    }

    public function test_an_order_is_found_by_the_family_name_of_the_customer(): void
    {
        $customer = User::factory()->create(['last_name' => 'Marchand']);
        $order = $this->order($customer);

        $found = $this->search('Marchand')->viewData('orders');

        $this->assertSame([$order->id], $found->pluck('id')->all());
    }

    public function test_an_archived_order_is_not_offered(): void
    {
        // Archiving is how the working list is tidied: an order put away
        // must not come back through the search box.
        $customer = User::factory()->create();
        $open = $this->order($customer);
        $this->order($customer, ['archived_at' => now()]);

        $found = $this->search($customer->last_name)->viewData('orders');

        $this->assertSame([$open->id], $found->pluck('id')->all());
    }

    public function test_a_customer_is_found_by_email(): void
    {
        $customer = User::factory()->create(['email' => 'elodie@example.com']);

        $found = $this->search('elodie@example.com')->viewData('customers');

        $this->assertSame([$customer->id], $found->pluck('id')->all());
    }

    public function test_another_admin_is_never_a_search_result(): void
    {
        User::factory()->admin()->create(['first_name' => 'Camille', 'last_name' => 'Lemoine']);

        $found = $this->search('Lemoine')->viewData('customers');

        $this->assertCount(0, $found);
    }

    public function test_a_product_is_found_by_its_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'CAG-MCFOREST-BREATH']);
        Product::factory()->create(['sku' => 'OTHER-SKU']);

        $found = $this->search('CAG-MCFOREST')->viewData('products');

        $this->assertSame([$product->id], $found->pluck('id')->all());
    }

    public function test_a_term_that_matches_nothing_says_so(): void
    {
        $this->search('zzzz-no-such-thing')
            ->assertSee('No matches for')
            ->assertSee('zzzz-no-such-thing');
    }

    public function test_each_section_stops_at_ten_rows(): void
    {
        // The box is a shortcut, not a list page: twelve matching customers
        // must not push the products section off the screen.
        User::factory()->count(12)->create(['last_name' => 'Duplicat']);

        $found = $this->search('Duplicat')->viewData('customers');

        $this->assertCount(10, $found);
    }
}
