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

    /** @return array<string, mixed> */
    private function manualOrder(array $relay, string $action = 'placed', ?string $carrierSlug = 'vinted-go'): array
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();

        return [
            'action' => $action,
            'customer_mode' => 'existing',
            'customer_id' => User::factory()->create()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'carrier_id' => Carrier::query()->where('slug', $carrierSlug)->value('id'),
            'relay' => $relay + ['name' => '', 'line1' => '', 'postal_code' => '', 'city' => '', 'slug' => ''],
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'line1' => '12 rue des Lilas',
            'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR',
            'billing_first_name' => 'Jean', 'billing_last_name' => 'Dupont', 'billing_line1' => '12 rue des Lilas',
            'billing_postal_code' => '31000', 'billing_city' => 'Toulouse', 'billing_country' => 'FR',
        ];
    }

    /** The name is the shop's, not the form's: whatever is typed, it is "Locker Vinted Go". */
    public function test_the_manual_form_always_names_the_relay_locker_vinted_go(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.store'), $this->manualOrder([
                'name' => 'Jean Dupont', 'line1' => '12 rue des Lilas', 'postal_code' => '31000', 'city' => 'Toulouse',
            ]))
            ->assertSessionHasNoErrors();

        $relay = Order::query()->latest('id')->firstOrFail()->relay_snapshot;
        $this->assertSame('Locker Vinted Go', $relay['name']);
        $this->assertSame('12 rue des Lilas', $relay['line1']);
    }

    /** No name asked for: placing needs only the address. */
    public function test_placing_needs_no_relay_name(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.store'), $this->manualOrder([
                'line1' => '1 avenue de la Gare', 'postal_code' => '31000', 'city' => 'Toulouse',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Locker Vinted Go', Order::query()->latest('id')->firstOrFail()->relay_snapshot['name']);
    }

    public function test_placing_still_needs_the_relay_address(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.store'), $this->manualOrder([]))
            ->assertSessionHasErrors(['relay.line1', 'relay.postal_code', 'relay.city'])
            ->assertSessionDoesntHaveErrors('relay.name');
    }

    public function test_the_api_cannot_rename_it_either(): void
    {
        $payload = $this->manualOrder(['name' => 'Autre chose', 'line1' => '1 avenue de la Gare', 'postal_code' => '31000', 'city' => 'Toulouse'], 'draft');
        unset($payload['action']);

        $this->postJson('/api/admin/orders', $payload, ['Authorization' => 'Bearer test-admin-api-token'])->assertStatus(201);
        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('Locker Vinted Go', $order->relay_snapshot['name']);

        $payload['relay']['name'] = 'Encore autre chose';
        $this->patchJson('/api/admin/orders/'.$order->number, $payload, ['Authorization' => 'Bearer test-admin-api-token'])->assertOk();
        $this->assertSame('Locker Vinted Go', $order->fresh()->relay_snapshot['name']);
    }

    /** Another relay carrier keeps the name it is given. */
    public function test_other_relay_carriers_keep_their_name(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.store'), $this->manualOrder([
                'name' => 'RELAIS TABAC DU CENTRE', 'line1' => '5 place du Marché', 'postal_code' => '69002', 'city' => 'Lyon',
            ], carrierSlug: 'mondial-relay'))
            ->assertSessionHasNoErrors();

        $this->assertSame('RELAIS TABAC DU CENTRE', Order::query()->latest('id')->firstOrFail()->relay_snapshot['name']);
    }

    /** The form shows the name locked for Vinted Go. */
    public function test_the_form_locks_the_name_for_vinted_go(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.create'))->assertOk()->getContent();

        $this->assertStringContainsString('"vinted-go":"Locker Vinted Go"', $html);
        $this->assertStringContainsString('relayNameInput.readOnly = true', $html);
    }

    /** Orders saved before the rule get the name; their address stays. */
    public function test_existing_vinted_go_orders_are_renamed(): void
    {
        $address = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'line1' => '12 rue des Lilas', 'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR'];
        $make = fn (string $slug, array $relay) => Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'shipped',
            'address_snapshot' => $address,
            'carrier_id' => Carrier::query()->where('slug', $slug)->value('id'),
            'carrier_method' => 'relay',
            'carrier_snapshot' => ['slug' => $slug, 'name' => ['fr' => $slug]],
            'relay_snapshot' => $relay,
            'subtotal_cents' => 450, 'shipping_cents' => 0, 'discount_cents' => 0, 'total_cents' => 450,
            'payment_method' => 'card',
        ]);
        $vinted = $make('vinted-go', ['slug' => null, 'name' => 'Jean Dupont', 'line1' => '12 rue des Lilas', 'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR', 'hours' => null]);
        $mondial = $make('mondial-relay', ['slug' => null, 'name' => 'Tabac du coin', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR', 'hours' => null]);

        (require database_path('migrations/2026_09_28_180000_name_vinted_go_relay_points.php'))->up();

        $this->assertSame('Locker Vinted Go', $vinted->fresh()->relay_snapshot['name']);
        $this->assertSame('12 rue des Lilas', $vinted->fresh()->relay_snapshot['line1']);
        $this->assertSame('Tabac du coin', $mondial->fresh()->relay_snapshot['name']);
    }
}
