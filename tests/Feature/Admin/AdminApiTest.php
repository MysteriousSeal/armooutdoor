<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Order;
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

    /**
     * StoreDraftOrderRequest spreads the admin request's rules, so the payment
     * method reaches the API without a line of its own. That is worth a test
     * rather than a reading: the inheritance is what would break silently if
     * either request stopped spreading the other.
     */
    public function test_draft_order_records_how_it_was_paid(): void
    {
        $product = Product::factory()->create(['price_cents' => 2000, 'quantity' => 10]);

        $this->postJson(
            '/api/admin/orders',
            [...$this->draftOrderPayload($product, 1), 'payment_method' => 'paypal'],
            $this->headers(),
        )->assertStatus(201);

        $this->assertSame(PaymentMethod::PayPal, Order::query()->latest('id')->first()->payment_method);
    }

    public function test_draft_order_refuses_an_unknown_payment_method(): void
    {
        $product = Product::factory()->create(['price_cents' => 2000, 'quantity' => 10]);

        $this->postJson(
            '/api/admin/orders',
            [...$this->draftOrderPayload($product, 1), 'payment_method' => 'bitcoin'],
            $this->headers(),
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_draft_order_creation_fails_with_insufficient_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 1]);

        $this->postJson('/api/admin/orders', $this->draftOrderPayload($product, 99), $this->headers())
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * A line the catalogue does not carry: a name, a price, a quantity, and
     * optionally a SKU, a variant label and a weight. It sits next to a
     * catalogue line on the same draft.
     */
    public function test_a_draft_can_mix_catalogue_and_off_catalogue_lines(): void
    {
        $product = Product::factory()->create(['price_cents' => 2000, 'quantity' => 10]);
        $payload = $this->draftOrderPayload($product, 1);
        $payload['items'][] = [
            'name' => 'Holster sur mesure',
            'price' => '45.50',
            'quantity' => 2,
            'sku' => 'CUSTOM-HOL-1',
            'variant_label' => 'Gaucher',
            'weight_grams' => 300,
        ];

        $this->postJson('/api/admin/orders', $payload, $this->headers())->assertStatus(201);

        $order = Order::query()->latest('id')->firstOrFail();
        $custom = $order->items->firstWhere('is_custom', true);

        $this->assertSame(2, $order->items->count());
        $this->assertNull($custom->product_id);
        $this->assertSame('Holster sur mesure', $custom->localizedName());
        $this->assertSame('CUSTOM-HOL-1', $custom->sku);
        $this->assertSame('Gaucher', $custom->variant_label);
        $this->assertSame(4550, $custom->unit_price_cents);
        $this->assertSame(9100, $custom->line_cents);
        $this->assertSame(2000 + 9100, $order->subtotal_cents);
    }

    public function test_a_draft_can_hold_only_off_catalogue_lines(): void
    {
        $product = Product::factory()->create();
        $payload = $this->draftOrderPayload($product, 1);
        $payload['items'] = [['name' => 'Réparation', 'price' => '20', 'quantity' => 1]];

        $this->postJson('/api/admin/orders', $payload, $this->headers())->assertStatus(201);

        $this->assertTrue(Order::query()->latest('id')->firstOrFail()->hasCustomLines());
    }

    public function test_an_off_catalogue_line_needs_a_price_and_a_quantity(): void
    {
        $product = Product::factory()->create();
        $payload = $this->draftOrderPayload($product, 1);
        $payload['items'] = [['name' => 'Réparation']];

        $this->postJson('/api/admin/orders', $payload, $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.price', 'items.0.quantity']);

        $this->assertDatabaseCount('orders', 0);
    }

    /** Its weight counts toward the automatic shipping price. */
    public function test_an_off_catalogue_line_weighs_in_on_shipping(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000, 'quantity' => 10, 'weight_grams' => 100]);
        Carrier::query()->where('slug', 'colissimo-home')->firstOrFail()
            ->priceTiers()->create(['min_weight_grams' => 5000, 'price_cents' => 2500]);
        $light = $this->draftOrderPayload($product, 1);
        $light['items'][] = ['name' => 'Caisse', 'price' => '10', 'quantity' => 1, 'weight_grams' => 0];
        $heavy = $light;
        $heavy['items'][1]['weight_grams'] = 20000;

        $this->postJson('/api/admin/orders', $light, $this->headers())->assertStatus(201);
        $lightShipping = Order::query()->latest('id')->firstOrFail()->shipping_cents;
        $this->postJson('/api/admin/orders', $heavy, $this->headers())->assertStatus(201);
        $heavyShipping = Order::query()->latest('id')->firstOrFail()->shipping_cents;

        $this->assertGreaterThan($lightShipping, $heavyShipping);
    }

    /** Validating the draft takes no stock for a line with no product. */
    public function test_validating_a_draft_with_an_off_catalogue_line_takes_stock_only_for_the_catalogue(): void
    {
        $product = Product::factory()->create(['price_cents' => 2000, 'quantity' => 10]);
        $payload = $this->draftOrderPayload($product, 3);
        $payload['items'][] = ['name' => 'Holster sur mesure', 'price' => '45', 'quantity' => 1];
        $this->postJson('/api/admin/orders', $payload, $this->headers())->assertStatus(201);
        $order = Order::query()->latest('id')->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.validate-draft', $order))
            ->assertRedirect();

        $this->assertSame('placed', $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->quantity);

        // No product and no image behind the line: the documents still print.
        $order = $order->fresh(['items.product', 'items.variant']);
        foreach (['admin.orders.invoice-pdf', 'admin.orders.delivery-slip-pdf'] as $view) {
            $html = view($view, ['order' => $order, 'company' => CompanySetting::current()])->render();
            $this->assertStringContainsString('Holster sur mesure', $html, $view);
        }
    }

    /** The web form cannot show such a line, so it would drop it on save. */
    public function test_the_web_edit_form_refuses_a_draft_with_off_catalogue_lines(): void
    {
        $product = Product::factory()->create(['price_cents' => 2000, 'quantity' => 10]);
        $payload = $this->draftOrderPayload($product, 1);
        $payload['items'][] = ['name' => 'Holster sur mesure', 'price' => '45', 'quantity' => 1];
        $this->postJson('/api/admin/orders', $payload, $this->headers())->assertStatus(201);
        $order = Order::query()->latest('id')->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.orders.edit', $order))
            ->assertRedirect(route('admin.orders.show', $order));
        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Holster sur mesure')
            ->assertDontSee(route('admin.orders.edit', $order), false);
        $this->actingAs($admin)->put(route('admin.orders.update', $order), [...$payload, 'action' => 'draft'])
            ->assertStatus(409);
        $this->assertSame(2, $order->fresh()->items->count());
    }

    /** The web form keeps its rule: every line is a catalogue product. */
    public function test_the_web_form_ignores_a_line_without_a_product(): void
    {
        $product = Product::factory()->create();
        $payload = $this->draftOrderPayload($product, 1);
        $payload['items'] = [['name' => 'Réparation', 'price' => '20', 'quantity' => 1]];

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.store'), [...$payload, 'action' => 'draft'])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    /** A PATCH through the API can change the off-catalogue lines. */
    public function test_a_draft_with_off_catalogue_lines_can_be_updated_via_the_api(): void
    {
        $product = Product::factory()->create(['price_cents' => 2000, 'quantity' => 10]);
        $payload = $this->draftOrderPayload($product, 1);
        $payload['items'][] = ['name' => 'Holster sur mesure', 'price' => '45', 'quantity' => 1];
        $this->postJson('/api/admin/orders', $payload, $this->headers())->assertStatus(201);
        $order = Order::query()->latest('id')->firstOrFail();

        $payload['items'][1]['price'] = '50';
        $this->patchJson('/api/admin/orders/'.$order->number, $payload, $this->headers())->assertOk();

        $this->assertSame(5000, $order->fresh()->items->firstWhere('is_custom', true)->unit_price_cents);
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
