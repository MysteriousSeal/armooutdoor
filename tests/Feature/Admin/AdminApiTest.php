<?php

namespace Tests\Feature\Admin;

use App\Models\Carrier;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'test-admin-api-token']);
        $this->seed(ShippingSeeder::class);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-admin-api-token'];
    }

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/admin/products')
            ->assertStatus(401);
    }

    public function test_requests_with_an_invalid_token_are_rejected(): void
    {
        $this->getJson('/api/admin/products', ['Authorization' => 'Bearer wrong-token'])
            ->assertStatus(401);
    }

    public function test_categories_index_returns_json(): void
    {
        Category::factory()->create();

        $this->getJson('/api/admin/categories', $this->headers())
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'parent_id', 'products']]]);
    }

    public function test_products_index_returns_a_paginated_list(): void
    {
        Product::factory()->count(3)->create();

        // Enveloppe unique pour toute l'API : les données sous `data`, la
        // pagination sous `meta`, comme les ressources Laravel.
        $this->getJson('/api/admin/products', $this->headers())
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'name']], 'meta' => ['current_page', 'last_page', 'total']]);
    }

    public function test_products_show_returns_a_single_product(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/admin/products/'.$product->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_products_update_persists_changes(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000, 'quantity' => 5]);

        $this->patchJson('/api/admin/products/'.$product->id, [
            'price' => 25.50,
            'quantity' => 12,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.price_cents', 2550)
            ->assertJsonPath('data.quantity', 12);

        $this->assertSame(2550, $product->fresh()->price_cents);
        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_products_update_validates_input(): void
    {
        $product = Product::factory()->create();

        $this->patchJson('/api/admin/products/'.$product->id, [
            'price' => -5,
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    private function draftOrderPayload(Product $product, int $quantity): array
    {
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $customer = User::factory()->create();

        return [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'first_name' => 'Jean',
            'last_name' => 'Martin',
            'line1' => '1 rue de Test',
            'postal_code' => '75000',
            'city' => 'Paris',
            'country' => 'FR',
            'billing_first_name' => 'Jean',
            'billing_last_name' => 'Martin',
            'billing_line1' => '1 rue de Test',
            'billing_postal_code' => '75000',
            'billing_city' => 'Paris',
            'billing_country' => 'FR',
            'carrier_id' => $carrier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => $quantity],
            ],
        ];
    }

    public function test_draft_order_can_be_created_via_the_api(): void
    {
        $product = Product::factory()->create(['price_cents' => 2000, 'quantity' => 10]);

        $response = $this->postJson('/api/admin/orders', $this->draftOrderPayload($product, 2), $this->headers());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('orders', ['status' => 'draft']);
    }

    public function test_draft_order_creation_fails_with_insufficient_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 1]);

        $this->postJson('/api/admin/orders', $this->draftOrderPayload($product, 99), $this->headers())
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admins_index_lists_admin_users_only(): void
    {
        User::factory()->admin()->create();
        User::factory()->create();

        $response = $this->getJson('/api/admin/admins', $this->headers())->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_admins_update_persists_info_and_password(): void
    {
        $admin = User::factory()->staffAdmin()->create(['email' => 'staff@example.com']);

        $this->patchJson('/api/admin/admins/'.$admin->id, [
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Jean')
            ->assertJsonPath('data.email', 'jean@example.com');

        $admin->refresh();
        $this->assertSame('jean@example.com', $admin->email);
        $this->assertTrue(Hash::check('a-new-password', $admin->password));
    }

    public function test_admins_update_cannot_demote_the_last_owner(): void
    {
        $owner = User::factory()->admin()->create();

        $this->patchJson('/api/admin/admins/'.$owner->id, ['role' => 'staff'], $this->headers())
            ->assertStatus(422);

        $this->assertSame('owner', $owner->fresh()->role);
    }

    public function test_admins_update_rejects_a_non_admin_user(): void
    {
        $user = User::factory()->create();

        $this->patchJson('/api/admin/admins/'.$user->id, ['first_name' => 'X'], $this->headers())
            ->assertStatus(404);
    }
}
