<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Product;
use App\Models\ShippingSetting;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The road to free shipping, drawn on the cart: how many euros remain,
 * said and barred - and nothing at all where no threshold exists.
 */
class CartFreeShippingBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function configureThreshold(int $cents): void
    {
        $carrier = Carrier::query()->create([
            'slug' => 'colissimo-home', 'name' => ['fr' => 'Colissimo'], 'description' => ['fr' => ''],
            'eta' => ['fr' => '2–4 jours'], 'method' => 'home', 'price_cents' => 690, 'active' => true,
        ]);
        ShippingSetting::current()->update([
            'free_shipping_threshold_cents' => $cents,
            'free_shipping_carrier_ids' => [$carrier->id],
        ]);
    }

    private function cartWith(string $slug, int $quantity = 1): void
    {
        $product = Product::query()->where('slug', $slug)->firstOrFail();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => $quantity]);
    }

    public function test_below_the_line_the_bar_says_what_remains(): void
    {
        // The tent costs 349,00; the bar aims at 400,00.
        $this->configureThreshold(40000);
        $this->cartWith('ridge-tent');

        $this->get('/cart')->assertOk()
            ->assertSee('Encore 51,00')
            ->assertSee('aria-valuenow="87"', false);
    }

    public function test_past_the_line_the_bar_celebrates(): void
    {
        $this->configureThreshold(40000);
        $this->cartWith('ridge-tent', 2);

        $this->get('/cart')->assertOk()
            ->assertSee('Livraison gratuite débloquée')
            ->assertSee('aria-valuenow="100"', false)
            ->assertDontSee('Encore');
    }

    public function test_a_quantity_update_hands_the_bar_its_new_state(): void
    {
        $this->configureThreshold(40000);
        $this->cartWith('ridge-tent');
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        // One tent short of the line, two tents past it.
        $this->patchJson('/cart/'.$product->slug, ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('freeShippingBar.reached', true)
            ->assertJsonPath('freeShippingBar.progress', 100);

        $this->patchJson('/cart/'.$product->slug, ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('freeShippingBar.reached', false)
            ->assertJsonPath('freeShippingBar.progress', 87);
    }

    public function test_no_threshold_means_no_bar(): void
    {
        $this->cartWith('ridge-tent');

        $this->get('/cart')->assertOk()->assertDontSee('cart-free-shipping');
    }
}
