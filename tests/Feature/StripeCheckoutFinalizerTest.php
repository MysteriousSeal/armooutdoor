<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\RelayPoint;
use App\Models\ShippingSetting;
use App\Models\User;
use App\Services\StripeCheckoutFinalizer;
use App\Support\Cart;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The card path builds the order *after* Stripe has taken the money, in a
 * different place from the PayPal path. The order it writes must therefore
 * agree with what startStripeCheckout() charged — nothing reconciles the two
 * afterwards, so a mismatch is money quietly unaccounted for.
 */
class StripeCheckoutFinalizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
    }

    /**
     * What startStripeCheckout() puts on the Stripe session — the figure the
     * customer is actually charged.
     */
    private function chargedByStripe(Cart $cart, Carrier $carrier, ?DiscountCode $code, ?User $user): int
    {
        $subtotal = $cart->totalCents();
        $shipping = ShippingSetting::current()->effectivePriceCents($carrier, $subtotal, $cart->totalWeightGrams());

        $usable = $code !== null && $code->eligibilityError($user) === null;
        $discountCents = $usable ? $subtotal - $code->apply($subtotal) : 0;
        $shippingDiscount = $usable ? $code->shippingDiscountCents($carrier, $shipping, $subtotal) : 0;

        return max(0, $subtotal - $discountCents + $shipping - $shippingDiscount);
    }

    private function fillCart(User $user, string $slug = 'cast-iron-skillet'): Cart
    {
        $product = Product::query()->where('slug', $slug)->firstOrFail();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        return app(Cart::class);
    }

    public function test_a_relay_order_with_a_free_delivery_code_records_what_stripe_charged(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'mondial-relay')->firstOrFail();
        $relay = RelayPoint::query()->firstOrFail();
        $code = DiscountCode::query()->create([
            'code' => 'RELAIS',
            'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            'value' => null,
        ]);

        $cart = $this->fillCart($user);
        $charged = $this->chargedByStripe($cart, $carrier, $code, $user);

        $order = app(StripeCheckoutFinalizer::class)->finalize(
            $user->id, $address->id, null, $carrier->id, $relay->id, $code->id, 'cs_test_1', 'pi_test_1',
        );

        $this->assertSame($charged, $order->total_cents, 'Order total must equal what Stripe charged.');
        $this->assertGreaterThan(0, $order->shipping_discount_cents);
        $this->assertSame($order->shipping_cents, $order->shipping_discount_cents);
        $this->assertSame(0, $order->discount_cents);
    }

    public function test_a_percentage_code_still_records_what_stripe_charged(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $code = DiscountCode::query()->create(['code' => 'DIX', 'type' => 'percentage', 'value' => 10]);

        $cart = $this->fillCart($user);
        $charged = $this->chargedByStripe($cart, $carrier, $code, $user);

        $order = app(StripeCheckoutFinalizer::class)->finalize(
            $user->id, $address->id, null, $carrier->id, null, $code->id, 'cs_test_2', 'pi_test_2',
        );

        $this->assertSame($charged, $order->total_cents);
        $this->assertGreaterThan(0, $order->discount_cents);
        $this->assertSame(0, $order->shipping_discount_cents);
    }

    public function test_an_order_without_a_code_records_what_stripe_charged(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $cart = $this->fillCart($user);
        $charged = $this->chargedByStripe($cart, $carrier, null, $user);

        $order = app(StripeCheckoutFinalizer::class)->finalize(
            $user->id, $address->id, null, $carrier->id, null, null, 'cs_test_3', 'pi_test_3',
        );

        $this->assertSame($charged, $order->total_cents);
        $this->assertSame(0, $order->shipping_discount_cents);
    }

    public function test_a_free_delivery_code_does_nothing_on_a_home_delivery_card_order(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $code = DiscountCode::query()->create([
            'code' => 'RELAIS',
            'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            'value' => null,
        ]);

        $cart = $this->fillCart($user);
        $charged = $this->chargedByStripe($cart, $carrier, $code, $user);

        $order = app(StripeCheckoutFinalizer::class)->finalize(
            $user->id, $address->id, null, $carrier->id, null, $code->id, 'cs_test_4', 'pi_test_4',
        );

        $this->assertSame($charged, $order->total_cents);
        $this->assertSame(0, $order->shipping_discount_cents);
        $this->assertSame($order->subtotal_cents + $order->shipping_cents, $order->total_cents);
    }

    public function test_a_code_worth_nothing_by_payment_time_is_not_consumed(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $code = DiscountCode::query()->create([
            'code' => 'RELAIS',
            'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            'value' => null,
            'quantity' => 1,
        ]);

        $this->fillCart($user);

        $order = app(StripeCheckoutFinalizer::class)->finalize(
            $user->id, $address->id, null, $carrier->id, null, $code->id, 'cs_test_5', 'pi_test_5',
        );

        $this->assertNull($order->discount_code_id);
        $this->assertSame(1, $code->fresh()->quantity, 'The code should still be usable.');
    }

    public function test_a_limited_code_is_still_consumed_when_it_is_worth_something(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'mondial-relay')->firstOrFail();
        $relay = RelayPoint::query()->firstOrFail();
        $code = DiscountCode::query()->create([
            'code' => 'RELAIS',
            'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            'value' => null,
            'quantity' => 1,
        ]);

        $this->fillCart($user);

        $order = app(StripeCheckoutFinalizer::class)->finalize(
            $user->id, $address->id, null, $carrier->id, $relay->id, $code->id, 'cs_test_6', 'pi_test_6',
        );

        $this->assertSame($code->id, $order->discount_code_id);
        $this->assertSame(0, $code->fresh()->quantity);
    }

    public function test_finalizing_the_same_session_twice_returns_the_first_order(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->fillCart($user);

        $finalizer = app(StripeCheckoutFinalizer::class);
        $first = $finalizer->finalize($user->id, $address->id, null, $carrier->id, null, null, 'cs_dup', 'pi_dup');
        $second = $finalizer->finalize($user->id, $address->id, null, $carrier->id, null, null, 'cs_dup', 'pi_dup');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->count());
    }
}
