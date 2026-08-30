<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le poids maximum d'un transporteur : au-delà, il reste affiché au
 * checkout, grisé et expliqué, mais ne se choisit plus — et le serveur
 * refuse la commande même envoyée à la main.
 */
class CarrierMaxWeightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
    }

    private function cartOfWeight(User $user, int $weightGrams): Product
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $product->update(['weight_grams' => $weightGrams]);

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        return $product;
    }

    public function test_a_carrier_over_the_limit_shows_greyed_with_the_reason(): void
    {
        $user = User::factory()->create();
        Address::factory()->for($user)->create();
        $this->cartOfWeight($user, 5000);
        Carrier::query()->where('slug', 'colissimo-home')->firstOrFail()->update(['max_weight_grams' => 4000]);

        $html = $this->actingAs($user)->get('/checkout')->assertOk()->getContent();

        $this->assertStringContainsString(__('store.carrier_too_heavy'), $html);

        // L'input du transporteur, pas celui d'une adresse qui porterait le
        // même nombre : on l'isole par son name et sa value ensemble.
        $carrierId = Carrier::query()->where('slug', 'colissimo-home')->value('id');
        preg_match(
            '/<input[^>]*name="carrier_id"[^>]*value="'.$carrierId.'"[^>]*>/s',
            $html,
            $cardInput,
        );

        $this->assertNotEmpty($cardInput);
        $this->assertStringContainsString('disabled', $cardInput[0]);
    }

    public function test_a_carrier_under_the_limit_is_untouched(): void
    {
        $user = User::factory()->create();
        Address::factory()->for($user)->create();
        $this->cartOfWeight($user, 3000);
        Carrier::query()->where('slug', 'colissimo-home')->firstOrFail()->update(['max_weight_grams' => 4000]);

        $this->actingAs($user)->get('/checkout')
            ->assertOk()
            ->assertDontSee(__('store.carrier_too_heavy'));
    }

    public function test_the_server_refuses_an_order_on_a_too_heavy_carrier(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $this->cartOfWeight($user, 5000);
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $carrier->update(['max_weight_grams' => 4000]);

        $this->actingAs($user)
            ->post('/checkout', [
                'address_id' => $address->id,
                'same_billing_address' => true,
                'carrier_id' => $carrier->id,
                'payment_method' => 'paypal',
            ])
            ->assertSessionHasErrors('carrier_id');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_an_order_within_the_limit_still_goes_through(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $this->cartOfWeight($user, 3000);
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $carrier->update(['max_weight_grams' => 4000]);

        $this->actingAs($user)
            ->post('/checkout', [
                'address_id' => $address->id,
                'same_billing_address' => true,
                'carrier_id' => $carrier->id,
                'payment_method' => 'paypal',
            ])
            ->assertSessionDoesntHaveErrors('carrier_id');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_cart_estimate_ignores_a_carrier_the_weight_disqualifies(): void
    {
        $user = User::factory()->create();
        $this->cartOfWeight($user, 5000);

        // Le moins cher des transporteurs devient trop petit pour le
        // panier : le « à partir de » doit passer au suivant, pas
        // continuer d'afficher un prix qu'aucun choix ne permet.
        $carriers = Carrier::query()->active()->get();
        $cheapest = $carriers->sortBy(fn (Carrier $carrier) => $carrier->effectivePriceCentsForWeight(5000))->first();
        $cheapest->update(['max_weight_grams' => 4000]);

        $nextCents = $carriers
            ->reject(fn (Carrier $carrier) => $carrier->is($cheapest))
            ->map(fn (Carrier $carrier) => $carrier->effectivePriceCentsForWeight(5000))
            ->min();

        $this->actingAs($user)->get('/cart')
            ->assertOk()
            ->assertSee(__('store.shipping_from_amount', ['price' => format_euros($nextCents)]));
    }

    public function test_the_settings_list_shows_the_tier_count_and_the_limit(): void
    {
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $carrier->update(['max_weight_grams' => 4000]);
        $carrier->priceTiers()->delete();
        $carrier->priceTiers()->create(['min_weight_grams' => 1000, 'price_cents' => 890]);
        $carrier->priceTiers()->create(['min_weight_grams' => 2000, 'price_cents' => 1090]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings/shipping')
            ->assertOk()
            ->assertSee('2 tiers')
            ->assertSee('max 4,000 g')
            // Les autres transporteurs n'ont pas de limite : la liste le dit
            // plutôt que de laisser un blanc.
            ->assertSee('no max weight');
    }

    public function test_the_admin_modal_saves_and_clears_the_limit(): void
    {
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings/carriers/'.$carrier->id.'/price-tiers', [
                'default_price' => '6.90',
                'max_weight' => 4000,
            ])
            ->assertRedirect();

        $this->assertSame(4000, $carrier->fresh()->max_weight_grams);

        $this->actingAs($admin)
            ->put('/admin/settings/carriers/'.$carrier->id.'/price-tiers', [
                'default_price' => '6.90',
                'max_weight' => '',
            ])
            ->assertRedirect();

        $this->assertNull($carrier->fresh()->max_weight_grams);
    }
}
