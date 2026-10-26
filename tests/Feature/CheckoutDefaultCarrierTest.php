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
 * The carrier ticked when the checkout opens: the cheapest one the cart can
 * go with, relay or not, as charged for this cart.
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

    public function test_a_cheaper_relay_beats_home_delivery(): void
    {
        // 1 kg: Mondial Relay and Lettre suivie are out; Shop2Shop at 4,50 €
        // beats Colissimo at 6,90 €.
        $this->cartOfWeight(1000);

        $this->assertSame('relais-pickup', $this->checkedCarrierSlug());
    }

    public function test_a_product_closed_to_lettre_suivie_opens_on_mondial_relay(): void
    {
        // Most of the catalogue: light, but not allowed in a letter. Mondial
        // Relay at 3,90 € beats Colissimo at 6,90 €.
        $this->cartOfWeight(200);
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $product->update(['carrier_ids' => Carrier::query()->whereIn('slug', ['colissimo-home', 'chronopost-home', 'mondial-relay', 'relais-pickup'])->pluck('id')->all()]);

        $this->assertSame('mondial-relay', $this->checkedCarrierSlug());
    }

    public function test_the_relay_picker_carries_its_required_notice(): void
    {
        $this->cartOfWeight(1000);

        $html = $this->actingAs($this->customer)->get('/checkout')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<p class="form-error checkout-required" id="relay-required" role="alert" hidden>#', $html);
        // A relay ticked by default leaves the order buttons usable: only a
        // missing address disables them on the server side.
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-checkout-submit[^>]*\sdisabled/s', $html);
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

    public function test_without_an_address_the_order_buttons_stay_usable_and_point_to_the_form(): void
    {
        $newcomer = User::factory()->create();
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $product->update(['weight_grams' => 200, 'price_cents' => 2000]);
        $this->actingAs($newcomer)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $html = $this->actingAs($newcomer)->get('/checkout')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-checkout-submit[^>]*\sdisabled/s', $html);
        $this->assertMatchesRegularExpression('#<p class="form-error checkout-required" id="address-required" role="alert" hidden>#', $html);
        // The new address starts from the account's own name, both halves.
        $this->assertMatchesRegularExpression('#id="first_name" name="first_name" class="form-control" value="'.preg_quote(e($newcomer->first_name), '#').'"#', $html);
        $this->assertMatchesRegularExpression('#id="last_name" name="last_name" class="form-control" value="'.preg_quote(e($newcomer->last_name), '#').'"#', $html);
        // No address, so no « nothing found for this address ».
        $this->assertStringContainsString('Enregistrez votre adresse pour voir les points relais proches', $html);
        $this->assertStringNotContainsString('Aucun point relais trouvé pour cette adresse.', $html);
    }

    public function test_saving_an_address_keeps_the_delivery_picked_so_far(): void
    {
        $newcomer = User::factory()->create();
        $this->actingAs($newcomer)->post('/cart', ['product_id' => Product::query()->where('slug', 'cast-iron-skillet')->value('id'), 'quantity' => 1]);
        $colissimo = Carrier::query()->where('slug', 'colissimo-home')->value('id');

        $this->actingAs($newcomer)->post('/checkout/addresses', [
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'line1' => '10 rue de Rivoli',
            'postal_code' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
            'phone' => '0600000000',
            'carrier_id' => $colissimo,
        ])->assertRedirect('/checkout?carrier_id='.$colissimo);
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
