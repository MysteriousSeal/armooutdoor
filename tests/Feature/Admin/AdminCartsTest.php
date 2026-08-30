<?php

namespace Tests\Feature\Admin;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCartsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): self
    {
        return $this->actingAs(User::factory()->admin()->create());
    }

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get('/admin/carts')->assertRedirect('/admin');
    }

    public function test_customers_cannot_open_the_carts_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/carts')
            ->assertRedirect(route('admin.login'));
    }

    public function test_an_admin_sees_carts_with_items_currently_in_them(): void
    {
        $shopper = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Dupont']);
        $product = Product::factory()->create(['price_cents' => 1000]);

        CartItem::create(['user_id' => $shopper->id, 'product_id' => $product->id, 'quantity' => 3]);

        $this->actingAsAdmin()->get('/admin/carts')->assertOk()
            ->assertSee($shopper->name)
            ->assertSee($product->localizedName())
            ->assertSee('30,00');
    }

    public function test_customers_with_an_empty_cart_are_not_listed(): void
    {
        $customer = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'SansPanier']);

        $this->actingAsAdmin()->get('/admin/carts')->assertOk()
            ->assertDontSee($customer->name)
            ->assertSee('No one has anything in their cart right now.');
    }

    public function test_the_search_filters_by_name_or_email(): void
    {
        $shopper = User::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Martin', 'email' => 'charlie@example.com']);
        $other = User::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);
        $product = Product::factory()->create();

        CartItem::create(['user_id' => $shopper->id, 'product_id' => $product->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $other->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAsAdmin()->get('/admin/carts?search=charlie')->assertOk()
            ->assertSee($shopper->name)
            ->assertDontSee($other->name);
    }
}
