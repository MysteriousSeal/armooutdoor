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

    public function test_the_summary_shows_text_not_a_zero_amount(): void
    {
        $this->code();
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/checkout/discount-code', ['code' => 'RELAIS']);

        $content = $this->actingAs($user)->get('/checkout')->assertOk()->getContent();

        // The code takes nothing off the goods, so the old markup rendered a
        // meaningless "-0,00 €" against the code's name.
        $this->assertMatchesRegularExpression(
            '/id="checkout-discount-value">\s*Point relais offert/',
            $content,
        );
    }

    public function test_the_dynamic_apply_returns_text_and_the_waived_carriers(): void
    {
        $this->code();
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)
            ->postJson('/checkout/discount-code', ['code' => 'RELAIS'])
            ->assertOk();

        $response->assertJsonPath('discountValueText', __('store.discount_code_free_relay_label'));

        // Without this the client could not zero the shipping line until a
        // reload, and would show a total the server disagrees with.
        $this->assertNotEmpty($response->json('freeShippingCarrierIds'));
    }

    public function test_an_amount_code_still_shows_its_amount(): void
    {
        DiscountCode::query()->create([
            'code' => 'DIX',
            'type' => DiscountCode::TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)
            ->postJson('/checkout/discount-code', ['code' => 'DIX'])
            ->assertOk();

        $this->assertStringStartsWith('-', $response->json('discountValueText'));
        $this->assertSame([], $response->json('freeShippingCarrierIds'));
    }

    private function orderWith(int $shippingCents, int $shippingDiscountCents): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'relay',
            'carrier_snapshot' => ['name' => ['fr' => 'Mondial Relay']],
            'subtotal_cents' => 5000,
            'shipping_cents' => $shippingCents,
            'shipping_discount_cents' => $shippingDiscountCents,
            'discount_cents' => 0,
            'total_cents' => 5000 + $shippingCents - $shippingDiscountCents,
            'payment_method' => 'card',
        ]);
    }

    public function test_delivery_waived_by_a_code_counts_as_free(): void
    {
        $order = $this->orderWith(490, 490);

        $this->assertSame(0, $order->chargedShippingCents());
        $this->assertTrue($order->deliveryWasFree());
        $this->assertTrue($order->deliveryWasFreedByCode());
    }

    public function test_delivery_free_from_the_threshold_is_not_credited_to_a_code(): void
    {
        $order = $this->orderWith(0, 0);

        $this->assertTrue($order->deliveryWasFree());
        $this->assertFalse($order->deliveryWasFreedByCode());
    }

    public function test_paid_delivery_is_not_free(): void
    {
        $order = $this->orderWith(490, 0);

        $this->assertSame(490, $order->chargedShippingCents());
        $this->assertFalse($order->deliveryWasFree());
    }

    public function test_the_orders_list_marks_a_code_waived_delivery(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orderWith(490, 490);

        // shipping_cents stays at the real carrier price, so the old
        // "shipping_cents === 0" test showed "No" on these orders.
        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('Yes (code)');
    }

    public function test_the_orders_list_marks_threshold_free_delivery_plainly(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orderWith(0, 0);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('>Yes<', false)
            ->assertDontSee('Yes (code)');
    }

    public function test_the_orders_list_marks_paid_delivery_as_not_free(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orderWith(490, 0);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('>No<', false)
            ->assertDontSee('Yes (code)');
    }

    private function waivedOrderFor(User $user): Order
    {
        $order = $this->orderWith(490, 490);
        $order->user_id = $user->id;
        $order->discount_code_snapshot = [
            'code' => 'RELAIS',
            'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            'value' => null,
        ];
        $order->save();

        return $order;
    }

    public function test_the_snapshot_identifies_a_free_delivery_code(): void
    {
        $order = $this->waivedOrderFor(User::factory()->create());

        $this->assertTrue($order->discountCodeWasFreeRelayShipping());
        $this->assertFalse($this->orderWith(490, 0)->discountCodeWasFreeRelayShipping());
    }

    public function test_the_admin_order_page_names_the_code_instead_of_a_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->waivedOrderFor(User::factory()->create());

        $content = $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Free relay delivery', $content);
        $this->assertStringNotContainsString('-0,00', $content);
    }

    public function test_the_customer_order_page_names_the_code_in_french(): void
    {
        $user = User::factory()->create();
        $order = $this->waivedOrderFor($user);

        $content = $this->actingAs($user)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('store.discount_code_free_relay_label'), $content);
        $this->assertStringNotContainsString('-0,00', $content);
        $this->assertStringNotContainsString('Free relay delivery', $content);
    }

    public function test_an_amount_code_still_shows_its_amount_on_the_order_page(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->orderWith(490, 0);
        $order->discount_cents = 500;
        $order->discount_code_snapshot = ['code' => 'DIX', 'type' => DiscountCode::TYPE_PERCENTAGE, 'value' => 10];
        $order->save();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            // format_euros() uses a non-breaking space, so build the expected
            // string with it rather than typing one by hand.
            ->assertSee('-'.format_euros(500))
            ->assertDontSee('Free relay delivery');
    }

    public function test_the_csv_export_reconciles_on_a_code_waived_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->orderWith(490, 490);

        $csv = $this->actingAs($admin)->get('/admin/orders/export')->streamedContent();

        [$header, $row] = array_map('str_getcsv', array_slice(explode("\n", trim($csv)), 0, 2));
        $line = array_combine($header, $row);

        $this->assertSame('4.90', $line['Shipping']);
        $this->assertSame('4.90', $line['Free delivery']);

        // Without the waiver column the row could not be balanced from its
        // own figures.
        $this->assertEqualsWithDelta(
            (float) $line['Total'],
            (float) $line['Subtotal'] - (float) $line['Discount'] + (float) $line['Shipping'] - (float) $line['Free delivery'],
            0.001,
        );

        $this->assertSame((float) $order->total_cents / 100, (float) $line['Total']);
    }

    public function test_the_csv_export_still_reconciles_without_a_waiver(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orderWith(490, 0);

        $csv = $this->actingAs($admin)->get('/admin/orders/export')->streamedContent();

        [$header, $row] = array_map('str_getcsv', array_slice(explode("\n", trim($csv)), 0, 2));
        $line = array_combine($header, $row);

        $this->assertSame('0.00', $line['Free delivery']);
        $this->assertEqualsWithDelta(
            (float) $line['Total'],
            (float) $line['Subtotal'] - (float) $line['Discount'] + (float) $line['Shipping'] - (float) $line['Free delivery'],
            0.001,
        );
    }

    public function test_an_existing_code_can_be_saved_from_the_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $code = $this->code();

        $this->actingAs($admin)
            ->put('/admin/discount-codes/'.$code->id, [
                'code' => 'RELAIS2',
                'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
                'value' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('RELAIS2', $code->fresh()->code);
    }

    public function test_a_stale_value_cannot_block_the_save(): void
    {
        $admin = User::factory()->admin()->create();
        $code = $this->code();

        // "0.00" is what the form used to render from a null value, and it
        // fails min:0.01 — which killed the Save button with no visible error.
        $this->actingAs($admin)
            ->put('/admin/discount-codes/'.$code->id, [
                'code' => 'RELAIS3',
                'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
                'value' => '0.00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('RELAIS3', $code->fresh()->code);
        $this->assertNull($code->fresh()->value);
    }

    public function test_the_edit_form_leaves_the_value_field_empty_and_disabled(): void
    {
        $admin = User::factory()->admin()->create();
        $code = $this->code();

        $content = $this->actingAs($admin)
            ->get('/admin/discount-codes/'.$code->id.'/edit')
            ->assertOk()
            ->getContent();

        preg_match('/<input[^>]*id="value".*?>/s', $content, $match);
        $input = $match[0] ?? '';

        $this->assertStringContainsString('value=""', $input);
        $this->assertStringContainsString('disabled', $input);
        $this->assertStringNotContainsString('required', $input);
    }

    public function test_an_amount_code_keeps_its_value_field_usable(): void
    {
        $admin = User::factory()->admin()->create();
        $code = DiscountCode::query()->create([
            'code' => 'DIX',
            'type' => DiscountCode::TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $content = $this->actingAs($admin)
            ->get('/admin/discount-codes/'.$code->id.'/edit')
            ->assertOk()
            ->getContent();

        preg_match('/<input[^>]*id="value".*?>/s', $content, $match);
        $input = $match[0] ?? '';

        $this->assertStringContainsString('value="10"', $input);
        $this->assertStringContainsString('required', $input);
        $this->assertStringNotContainsString('disabled', $input);
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
