<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\Discount;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    // --- Product discounts -------------------------------------------------

    public function test_an_active_percentage_discount_lowers_the_price(): void
    {
        $product = Product::factory()->create(['price_cents' => 10000]);
        Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 20,
        ]);

        $this->assertTrue($product->fresh()->hasDiscount());
        $this->assertSame(8000, $product->fresh()->effectivePriceCents());
    }

    public function test_a_fixed_discount_lowers_the_price_by_a_flat_amount(): void
    {
        $product = Product::factory()->create(['price_cents' => 10000]);
        Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'fixed',
            'value' => 1500,
        ]);

        $this->assertSame(8500, $product->fresh()->effectivePriceCents());
    }

    public function test_a_scheduled_discount_that_has_not_started_does_not_apply(): void
    {
        $product = Product::factory()->create(['price_cents' => 10000]);
        Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 50,
            'starts_at' => Carbon::now()->addDay(),
        ]);

        $this->assertFalse($product->fresh()->hasDiscount());
        $this->assertSame(10000, $product->fresh()->effectivePriceCents());
    }

    public function test_an_expired_discount_does_not_apply(): void
    {
        $product = Product::factory()->create(['price_cents' => 10000]);
        Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 50,
            'ends_at' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($product->fresh()->hasDiscount());
    }

    public function test_a_discount_never_takes_the_price_below_zero(): void
    {
        $product = Product::factory()->create(['price_cents' => 500]);
        Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'fixed',
            'value' => 5000,
        ]);

        $this->assertSame(0, $product->fresh()->effectivePriceCents());
    }

    public function test_product_page_shows_the_discounted_price(): void
    {
        $product = Product::factory()->create(['price_cents' => 10000, 'is_active' => true]);
        Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 25,
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('75,00') // discounted price
            ->assertSee('100,00'); // struck-through original price
    }

    // --- Discount codes ------------------------------------------------

    public function test_a_valid_percentage_code_is_applied_at_checkout(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_cents' => 10000]);
        $code = DiscountCode::query()->create(['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10]);
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)
            ->post('/checkout/discount-code', ['code' => 'save10'])
            ->assertRedirect('/checkout')
            ->assertSessionHas('status');

        $response = $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $carrier->id,
            'payment_method' => 'paypal',
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect('/orders/'.$order->number);

        $this->assertSame(10000, $order->subtotal_cents);
        $this->assertSame(1000, $order->discount_cents);
        $this->assertSame($code->id, $order->discount_code_id);
        $this->assertSame(10000 - 1000 + $order->shipping_cents, $order->total_cents);
        $this->assertSame(1, $code->fresh()->orders()->count());
    }

    public function test_a_limited_quantity_code_decrements_on_use(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_cents' => 5000]);
        $code = DiscountCode::query()->create(['code' => 'LIMITED', 'type' => 'fixed', 'value' => 500, 'quantity' => 3]);
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/checkout/discount-code', ['code' => 'LIMITED']);
        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $carrier->id,
            'payment_method' => 'paypal',
        ]);

        $this->assertSame(2, $code->fresh()->quantity);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout/discount-code', ['code' => 'NOPE'])
            ->assertSessionHasErrors('discount_code');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();
        DiscountCode::query()->create([
            'code' => 'OLDCODE',
            'type' => 'percentage',
            'value' => 10,
            'ends_at' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout/discount-code', ['code' => 'OLDCODE'])
            ->assertSessionHasErrors('discount_code');
    }

    public function test_a_sold_out_code_is_rejected(): void
    {
        $user = User::factory()->create();
        DiscountCode::query()->create([
            'code' => 'SOLDOUT',
            'type' => 'percentage',
            'value' => 10,
            'quantity' => 0,
        ]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout/discount-code', ['code' => 'SOLDOUT'])
            ->assertSessionHasErrors('discount_code');
    }

    public function test_a_code_restricted_to_another_customer_is_rejected(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        DiscountCode::query()->create([
            'code' => 'PRIVATE',
            'type' => 'percentage',
            'value' => 10,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout/discount-code', ['code' => 'PRIVATE'])
            ->assertSessionHasErrors('discount_code');
    }

    public function test_a_code_restricted_to_this_customer_is_accepted(): void
    {
        $user = User::factory()->create();
        DiscountCode::query()->create([
            'code' => 'MINE',
            'type' => 'percentage',
            'value' => 10,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/checkout/discount-code', ['code' => 'MINE'])
            ->assertRedirect('/checkout')
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();
    }

    public function test_a_customer_over_their_usage_limit_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_cents' => 5000]);
        $code = DiscountCode::query()->create([
            'code' => 'ONEUSE',
            'type' => 'percentage',
            'value' => 10,
            'max_uses_per_customer' => 1,
        ]);
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/checkout/discount-code', ['code' => 'ONEUSE']);
        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $carrier->id,
            'payment_method' => 'paypal',
        ]);

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout/discount-code', ['code' => 'ONEUSE'])
            ->assertSessionHasErrors('discount_code');

        $this->assertSame(1, $code->fresh()->customerUsageCount($user->id));
    }

    public function test_a_discount_code_can_be_removed(): void
    {
        $user = User::factory()->create();
        DiscountCode::query()->create(['code' => 'REMOVEME', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($user)->post('/checkout/discount-code', ['code' => 'REMOVEME']);

        $this->actingAs($user)
            ->delete('/checkout/discount-code')
            ->assertRedirect('/checkout')
            ->assertSessionHas('status');
    }
}
