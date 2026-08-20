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
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A discount code that waives delivery to a relay point. Unlike every other
 * code it touches the shipping line rather than the goods, and only once a
 * relay carrier is actually chosen.
 */
class FreeRelayShippingCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
    }

    private function code(): DiscountCode
    {
        return DiscountCode::query()->create([
            'code' => 'RELAIS',
            'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            'value' => null,
        ]);
    }

    private function relayCarrier(): Carrier
    {
        return Carrier::query()->active()->get()->first(fn (Carrier $c): bool => $c->isRelay());
    }

    private function homeCarrier(): Carrier
    {
        return Carrier::query()->active()->get()->first(fn (Carrier $c): bool => ! $c->isRelay());
    }

    /** Makes every relay carrier free above the given subtotal. */
    private function makeRelayFreeFrom(int $thresholdCents): void
    {
        $relayIds = Carrier::query()->active()->get()
            ->filter(fn (Carrier $c): bool => $c->isRelay())
            ->pluck('id')
            ->all();

        $setting = ShippingSetting::current();
        $setting->free_shipping_threshold_cents = $thresholdCents;
        $setting->free_shipping_carrier_ids = $relayIds;
        $setting->save();
    }

    public function test_it_leaves_the_goods_total_untouched(): void
    {
        $this->assertSame(5000, $this->code()->apply(5000));
    }

    public function test_it_waives_shipping_for_a_relay_carrier(): void
    {
        $carrier = Carrier::query()->where('slug', 'mondial-relay')->firstOrFail();

        $this->assertSame(490, $this->code()->shippingDiscountCents($carrier, 490, 1000));
    }

    public function test_it_does_nothing_for_a_home_carrier(): void
    {
        $this->assertSame(0, $this->code()->shippingDiscountCents($this->homeCarrier(), 490, 1000));
    }

    public function test_it_does_nothing_before_a_carrier_is_chosen(): void
    {
        $this->assertSame(0, $this->code()->shippingDiscountCents(null, 490, 1000));
    }

    public function test_it_is_refused_when_relay_delivery_is_already_free(): void
    {
        $this->makeRelayFreeFrom(1000);

        $this->assertNotNull($this->code()->cartEligibilityError(5000));
    }

    public function test_it_is_accepted_below_the_free_shipping_threshold(): void
    {
        $this->makeRelayFreeFrom(10000);

        $this->assertNull($this->code()->cartEligibilityError(5000));
    }

    public function test_it_is_accepted_when_only_some_relay_carriers_are_free(): void
    {
        $relays = Carrier::query()->active()->get()->filter(fn (Carrier $c): bool => $c->isRelay());

        if ($relays->count() < 2) {
            $this->markTestSkipped('Needs at least two relay carriers to be meaningful.');
        }

        // Free shipping covers one relay carrier but not the other, so the
        // code still has something to do.
        $setting = ShippingSetting::current();
        $setting->free_shipping_threshold_cents = 1000;
        $setting->free_shipping_carrier_ids = [$relays->first()->id];
        $setting->save();

        $this->assertNull($this->code()->cartEligibilityError(5000));
    }

    public function test_other_code_types_are_unaffected_by_the_cart_check(): void
    {
        $this->makeRelayFreeFrom(1000);

        $percentage = DiscountCode::query()->create([
            'code' => 'DIX',
            'type' => DiscountCode::TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $this->assertNull($percentage->cartEligibilityError(5000));
        $this->assertSame(0, $percentage->shippingDiscountCents($this->relayCarrier(), 490, 5000));
        $this->assertSame(4500, $percentage->apply(5000));
    }

    public function test_the_label_reads_as_free_delivery_not_an_amount(): void
    {
        $label = $this->code()->label();

        $this->assertStringNotContainsString('€', $label);
        $this->assertStringNotContainsString('%', $label);
    }

    public function test_the_admin_badge_is_english_and_the_storefront_is_french(): void
    {
        $code = $this->code();

        // The back office is English throughout; only the storefront is
        // translated. label() is the admin badge.
        $this->assertSame('Free relay delivery', $code->label());
        $this->assertSame('Point relais offert', __('store.discount_code_free_relay_label'));
    }

    public function test_the_edit_form_preview_shows_the_english_badge(): void
    {
        $admin = User::factory()->admin()->create();
        $code = $this->code();

        $content = $this->actingAs($admin)
            ->get('/admin/discount-codes/'.$code->id.'/edit')
            ->assertOk()
            ->assertDontSee('Point relais offert')
            ->getContent();

        // The radio's own label also reads "Free relay delivery", so assert on
        // the preview badge itself rather than anywhere on the page.
        $this->assertMatchesRegularExpression(
            '/id="discount-code-preview-badge">\s*Free relay delivery/',
            $content,
        );
    }

    public function test_the_create_form_preview_has_no_french_string(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/discount-codes/create')
            ->assertOk()
            ->assertDontSee('Point relais offert');
    }

    public function test_the_admin_list_shows_the_english_badge(): void
    {
        $admin = User::factory()->admin()->create();
        $this->code();

        $this->actingAs($admin)
            ->get('/admin/discounts?tab=codes')
            ->assertOk()
            ->assertSee('Free relay delivery')
            ->assertDontSee('Point relais offert');
    }

    public function test_the_checkout_refuses_it_when_relay_is_already_free(): void
    {
        $this->makeRelayFreeFrom(1);
        $this->code();

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->post('/checkout/discount-code', ['code' => 'RELAIS'])
            ->assertSessionHasErrors('discount_code');
    }

    public function test_the_checkout_accepts_it_when_relay_still_costs_money(): void
    {
        $this->code();

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->post('/checkout/discount-code', ['code' => 'RELAIS'])
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array{0: User, 1: Address}
     */
    private function readyToOrder(): array
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        return [$user, $address];
    }

    public function test_placing_a_relay_order_records_the_waiver_apart_from_the_goods_discount(): void
    {
        $this->code();
        [$user, $address] = $this->readyToOrder();
        $carrier = Carrier::query()->where('slug', 'mondial-relay')->firstOrFail();

        $this->actingAs($user)->post('/checkout/discount-code', ['code' => 'RELAIS']);

        $relayPoint = RelayPoint::query()->firstOrFail();

        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $carrier->id,
            'relay_point_id' => $relayPoint->id,
            'payment_method' => 'paypal',
        ]);

        $order = Order::query()->firstOrFail();

        // The waiver is its own line: folding it into discount_cents would
        // misreport the margin on every order that used one.
        $this->assertSame(0, $order->discount_cents);
        $this->assertSame($order->shipping_cents, $order->shipping_discount_cents);
        $this->assertGreaterThan(0, $order->shipping_discount_cents);
        $this->assertSame($order->subtotal_cents, $order->total_cents);
    }

    public function test_a_home_delivery_order_gets_no_waiver(): void
    {
        $this->code();
        [$user, $address] = $this->readyToOrder();

        $this->actingAs($user)->post('/checkout/discount-code', ['code' => 'RELAIS']);

        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $this->homeCarrier()->id,
            'payment_method' => 'paypal',
        ]);

        $order = Order::query()->firstOrFail();

        $this->assertSame(0, $order->shipping_discount_cents);
        $this->assertSame($order->subtotal_cents + $order->shipping_cents, $order->total_cents);
    }

    public function test_a_code_worth_nothing_by_checkout_is_not_consumed(): void
    {
        $code = $this->code();
        $code->quantity = 1;
        $code->save();

        [$user, $address] = $this->readyToOrder();
        $this->actingAs($user)->post('/checkout/discount-code', ['code' => 'RELAIS']);

        // The cart crosses the free-shipping threshold after the code was
        // applied, so by checkout the code is worth nothing.
        $this->makeRelayFreeFrom(1);

        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $this->homeCarrier()->id,
            'payment_method' => 'paypal',
        ]);

        $order = Order::query()->firstOrFail();

        $this->assertNull($order->discount_code_id);
        $this->assertSame(0, $order->shipping_discount_cents);
        $this->assertSame(1, $code->fresh()->quantity, 'The code should still be usable.');
    }

    public function test_an_admin_can_create_one_without_a_value(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/discount-codes', [
                'code' => 'RELAIS2',
                'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            ])
            ->assertRedirect();

        $created = DiscountCode::query()->where('code', 'RELAIS2')->firstOrFail();

        $this->assertTrue($created->isFreeRelayShipping());
        $this->assertNull($created->value);
    }

    public function test_a_value_is_still_required_for_the_amount_types(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([DiscountCode::TYPE_PERCENTAGE, DiscountCode::TYPE_FIXED] as $type) {
            $this->actingAs($admin)
                ->post('/admin/discount-codes', ['code' => 'NOVALUE', 'type' => $type])
                ->assertSessionHasErrors('value');
        }

        $this->assertDatabaseMissing('discount_codes', ['code' => 'NOVALUE']);
    }
}
