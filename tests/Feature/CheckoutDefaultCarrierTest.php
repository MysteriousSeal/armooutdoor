<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\Product;
use App\Models\ShippingSetting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The carrier ticked when the checkout opens: the cheapest home delivery the
 * cart can go with, so paying never waits on a relay point being picked.
 */
class CheckoutDefaultCarrierTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);

        // The production limits: Mondial Relay up to 350 g, Lettre suivie 300 g.
        Carrier::query()->update(['max_weight_grams' => null]);
        Carrier::query()->where('slug', 'mondial-relay')->update(['max_weight_grams' => 350]);
        Carrier::query()->where('slug', 'lettre-suivie')->update(['max_weight_grams' => 300]);

        $this->customer = User::factory()->create();
        Address::factory()->for($this->customer)->create();
    }

    private function cartOfWeight(int $weightGrams): void
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $product->update(['weight_grams' => $weightGrams, 'price_cents' => 2000]);

        $this->actingAs($this->customer)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
    }

    private function checkedCarrierSlug(): ?string
    {
        $html = $this->actingAs($this->customer)->get('/checkout')->assertOk()->getContent();

        preg_match_all('/<input[^>]*name="carrier_id"[^>]*>/s', $html, $inputs);

        foreach ($inputs[0] as $input) {
            if (preg_match('/\schecked\b/', $input) && preg_match('/value="(\d+)"/', $input, $value)) {
                return Carrier::query()->whereKey($value[1])->value('slug');
            }
        }

        return null;
    }

    public function test_a_light_cart_gets_the_cheapest_home_delivery(): void
    {
        // Lettre suivie at 3,50 € beats Mondial Relay at 3,90 €.
        $this->cartOfWeight(200);

        $this->assertSame('lettre-suivie', $this->checkedCarrierSlug());
    }

    public function test_home_delivery_is_ticked_even_when_a_relay_is_cheaper(): void
    {
        // 1 kg: Mondial Relay and Lettre suivie are out; Shop2Shop at 4,50 €
        // is cheaper, but Colissimo at 6,90 € needs no relay point picked.
        $this->cartOfWeight(1000);

        $this->assertSame('colissimo-home', $this->checkedCarrierSlug());
    }

    public function test_the_cheapest_is_judged_on_what_this_cart_pays(): void
    {
        // Colissimo free over 15 €: on this 20 € cart it costs nothing, so
        // it beats Shop2Shop's 4,50 €.
        $setting = ShippingSetting::current();
        $setting->free_shipping_threshold_cents = 1500;
        $setting->free_shipping_carrier_ids = [Carrier::query()->where('slug', 'colissimo-home')->value('id')];
        $setting->save();

        $this->cartOfWeight(1000);

        $this->assertSame('colissimo-home', $this->checkedCarrierSlug());
    }

    public function test_with_no_home_delivery_on_offer_the_cheapest_relay_is_ticked(): void
    {
        Carrier::query()->whereIn('slug', ['colissimo-home', 'chronopost-home', 'lettre-suivie'])->update(['active' => false]);
        $this->cartOfWeight(200);

        $this->assertSame('mondial-relay', $this->checkedCarrierSlug());
    }

    public function test_a_carrier_asked_for_in_the_url_still_wins(): void
    {
        $this->cartOfWeight(200);
        $colissimo = Carrier::query()->where('slug', 'colissimo-home')->value('id');

        $html = $this->actingAs($this->customer)->get('/checkout?carrier_id='.$colissimo)->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="carrier_id"[^>]*value="'.$colissimo.'"[^>]*\schecked\b/s', $html);
    }

    /** A second « Valider la commande » under the payment section, on the same form. */
    public function test_a_second_place_order_button_follows_the_payment_section(): void
    {
        $this->cartOfWeight(200);

        $html = $this->actingAs($this->customer)->get('/checkout')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-checkout-submit'));
        $this->assertSame(2, preg_match_all('/<button[^>]*type="submit"[^>]*form="checkout-form"[^>]*data-checkout-submit/s', $html));
        $this->assertGreaterThan(strpos($html, 'id="payment-section"'), strpos($html, 'class="cart-list-checkout checkout-main-submit"'));
        $this->assertLessThan(strpos($html, 'class="cart-summary checkout-summary"'), strpos($html, 'checkout-main-submit'));
    }

    /** Card is the only way to pay until PayPal opens, so it comes ticked. */
    public function test_card_payment_is_ticked_by_default(): void
    {
        $this->cartOfWeight(200);

        $html = $this->actingAs($this->customer)->get('/checkout')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="payment_method"[^>]*value="card"[^>]*\schecked\b/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="payment_method"[^>]*value="paypal"[^>]*\schecked\b/s', $html);
    }
}
