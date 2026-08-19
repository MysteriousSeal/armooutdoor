<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RelayPoint;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
    }

    public function test_guests_are_sent_to_login_before_checkout(): void
    {
        $this->get('/checkout')
            ->assertRedirect('/login');
    }

    public function test_an_empty_cart_cannot_be_checked_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/checkout')
            ->assertRedirect('/cart');
    }

    public function test_checkout_page_is_translated(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->get('/checkout')
            ->assertOk()
            ->assertSee('À domicile')
            ->assertSee('Point relais')
            ->assertSee('Colissimo')
            ->assertSee('Carte bancaire')
            ->assertSee('PayPal')
            ->assertDontSee('Stripe')
            ->assertSee('Nouvelle adresse');
    }

    public function test_a_user_can_create_an_address(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'daylight-pack')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout/addresses', [
                'label' => 'Home',
                'first_name' => 'Jean',
                'last_name' => 'Martin',
                'line1' => '12 rue des Archives',
                'postal_code' => '75004',
                'city' => 'Paris',
                'country' => 'FR',
                'phone' => '0612345678',
                'is_default' => '1',
            ])
            ->assertRedirect('/checkout')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'first_name' => 'Jean',
            'city' => 'Paris',
            'is_default' => 1,
        ]);
    }

    public function test_a_user_can_place_a_home_delivery_order(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $response = $this->actingAs($user)
            ->post('/checkout', [
                'address_id' => $address->id,
                'same_billing_address' => true,
                'carrier_id' => $carrier->id,
                'payment_method' => 'paypal',
            ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect('/orders/'.$order->number);

        $this->assertSame('placed', $order->status);
        $this->assertSame('paypal', $order->payment_method->value);
        $this->assertSame('home', $order->carrier_method->value);
        $this->assertSame(15800, $order->subtotal_cents);
        $this->assertSame(690, $order->shipping_cents);
        $this->assertSame(16490, $order->total_cents);
        $this->assertSame(1, $order->items()->count());
        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Poêle en fonte')
            ->assertSee('Colissimo')
            ->assertSee('PayPal');
    }

    public function test_a_relay_order_requires_a_pickup_point(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'merino-field-shirt')->firstOrFail();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'mondial-relay')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout', [
                'address_id' => $address->id,
                'same_billing_address' => true,
                'carrier_id' => $carrier->id,
                'payment_method' => 'card',
            ])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('relay_point_id');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_user_can_place_a_relay_order(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'enamel-camp-kettle')->firstOrFail();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'mondial-relay')->firstOrFail();
        $relay = RelayPoint::query()->where('slug', 'lyon-bellecour')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->post('/checkout', [
                'address_id' => $address->id,
                'same_billing_address' => true,
                'carrier_id' => $carrier->id,
                'relay_point_id' => $relay->id,
                'payment_method' => 'paypal',
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame('relay', $order->carrier_method->value);
        $this->assertSame('paypal', $order->payment_method->value);
        $this->assertSame($relay->id, $order->relay_point_id);
        $this->assertSame('Relais Bellecour', $order->relay_snapshot['name']);
        $this->assertSame(390, $order->shipping_cents);

        $this->actingAs($user)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee('Point relais')
            ->assertSee('Relais Bellecour')
            ->assertSee('Lyon')
            ->assertSee('PayPal');
    }

    public function test_checkout_records_the_selected_variant_and_decrements_its_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille M']],
            'price_cents' => 39900,
            'quantity' => 5,
            'is_active' => true,
        ]);
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]);

        $this->actingAs($user)
            ->post('/checkout', [
                'address_id' => $address->id,
                'same_billing_address' => true,
                'carrier_id' => $carrier->id,
                'payment_method' => 'paypal',
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame('Taille M', $item->variant_label);
        $this->assertSame(79800, $item->line_cents);
        $this->assertSame(3, $variant->fresh()->quantity);
        $this->assertSame(3, $product->fresh()->quantity, 'product quantity must mirror the sum of its variants, not stay frozen');
    }

    public function test_a_user_cannot_use_someone_elses_address(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $address = Address::factory()->for($stranger)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout', [
                'address_id' => $address->id,
                'carrier_id' => $carrier->id,
                'payment_method' => 'card',
            ])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('address_id');
    }

    public function test_an_order_requires_a_payment_method(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout', [
                'address_id' => $address->id,
                'carrier_id' => $carrier->id,
            ])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('payment_method');
    }
}
