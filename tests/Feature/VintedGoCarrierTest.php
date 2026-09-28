<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Models\Address;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingSetting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vinted Go: a carrier for manual orders only. Customers never see it in the
 * order funnel; the manual order form and the admin API can use it.
 */
class VintedGoCarrierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
        config(['services.admin_api.token' => 'test-admin-api-token']);
    }

    private function vintedGo(): Carrier
    {
        return Carrier::query()->where('slug', 'vinted-go')->firstOrFail();
    }

    private function cartFor(User $user): void
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_the_carrier_exists_as_a_manual_only_relay_at_no_charge(): void
    {
        $carrier = $this->vintedGo();

        $this->assertTrue($carrier->manual_only);
        $this->assertTrue($carrier->active);
        $this->assertSame(DeliveryMethod::Relay, $carrier->method);
        $this->assertSame(0, $carrier->price_cents);
        $this->assertSame('Vinted Go', $carrier->localizedName());
    }

    public function test_it_is_not_offered_at_checkout(): void
    {
        $user = User::factory()->create();
        Address::factory()->for($user)->create();
        $this->cartFor($user);

        $this->actingAs($user)->get('/checkout')
            ->assertOk()
            ->assertSee('Colissimo')
            ->assertDontSee('Vinted Go');
    }

    public function test_a_customer_order_cannot_use_it(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $this->cartFor($user);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout', [
                'address_id' => $address->id,
                'same_billing_address' => true,
                'carrier_id' => $this->vintedGo()->id,
                'payment_method' => 'paypal',
            ])
            ->assertSessionHasErrors('carrier_id');

        $this->assertSame(0, Order::query()->count());
    }

    /** Every customer-facing list goes through the same filter. */
    public function test_the_customer_carrier_list_leaves_it_out(): void
    {
        $this->assertNotContains('vinted-go', Carrier::query()->active()->pluck('slug')->all());
        $this->assertContains('vinted-go', Carrier::query()->usableInManualOrders()->pluck('slug')->all());
    }

    /** At 0 € it would otherwise read as free shipping in the product's structured data. */
    public function test_the_product_page_does_not_price_shipping_with_it(): void
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        $this->assertStringNotContainsString('Vinted Go', $html);
    }

    public function test_it_cannot_be_made_free_above_a_threshold(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('admin.settings.shipping.edit'))
            ->assertOk()
            ->assertSee('Manual orders only')
            ->getContent();

        // The free-shipping choices: every checkout carrier, never Vinted Go.
        preg_match('#<div class="shipping-carrier-options">(.*?)</div>\s*@?#s', $html, $options);
        $choices = $options[1] ?? '';
        $this->assertStringContainsString('value="'.Carrier::query()->where('slug', 'colissimo-home')->value('id').'"', $choices);
        $this->assertStringNotContainsString('value="'.$this->vintedGo()->id.'"', $choices);

        $this->actingAs($admin)
            ->put(route('admin.settings.shipping.update'), [
                'free_shipping_threshold' => '50',
                'free_shipping_carrier_ids' => [$this->vintedGo()->id],
            ])
            ->assertSessionHasErrors('free_shipping_carrier_ids.0');
    }

    public function test_the_product_form_does_not_list_it(): void
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Colissimo')
            ->assertDontSee('Vinted Go');
    }

    public function test_the_manual_order_form_offers_it(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->assertSee('Vinted Go');
    }

    /** A manual draft through the admin API, with the Vinted Go locker as relay point. */
    public function test_a_manual_order_can_use_it(): void
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $address = ['first_name' => 'Jean', 'last_name' => 'Martin', 'line1' => '1 rue de Test', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'];

        $this->postJson('/api/admin/orders', [
            'customer_mode' => 'existing',
            'customer_id' => User::factory()->create()->id,
            ...$address,
            'billing_first_name' => 'Jean', 'billing_last_name' => 'Martin', 'billing_line1' => '1 rue de Test',
            'billing_postal_code' => '75000', 'billing_city' => 'Paris', 'billing_country' => 'FR',
            'carrier_id' => $this->vintedGo()->id,
            'relay' => ['name' => 'Locker Vinted Go', 'line1' => '2 rue du Casier', 'postal_code' => '75001', 'city' => 'Paris'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], ['Authorization' => 'Bearer test-admin-api-token'])->assertStatus(201);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('vinted-go', $order->carrier_snapshot['slug']);
        $this->assertSame('Locker Vinted Go', $order->relay_snapshot['name']);
        $this->assertSame(0, $order->shipping_cents);
    }

    /** No known tracking page yet: the number stays plain text. */
    public function test_it_has_no_tracking_link_yet(): void
    {
        $this->assertNull($this->vintedGo()->trackingUrlFor('VG123456'));
    }
}
