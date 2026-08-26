<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Product;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le bloc livraison et paiement, sous le bouton d'achat. */
class ProductPerksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    private function page(): string
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'quantity' => 5,
            'carrier_ids' => Carrier::query()->pluck('id')->all(),
        ]);

        return $this->get('/products/'.$product->slug)->assertOk()->getContent();
    }

    public function test_the_shipping_title_is_said_once(): void
    {
        $html = $this->page();
        $perks = substr($html, strpos($html, 'product-detail-perks'));
        $perks = substr($perks, 0, strpos($perks, '</ul>'));

        // Répété par mode de livraison, il se lisait deux fois de suite pour
        // dire une seule chose.
        $this->assertSame(1, substr_count($perks, __('store.home_trust_ship_title')));
        $this->assertSame(2, substr_count($perks, 'product-perk-title'));
    }

    public function test_both_delivery_methods_are_still_listed(): void
    {
        $perks = $this->page();

        $this->assertStringContainsString(__('store.shipping_home').' :', $perks);
        $this->assertStringContainsString(__('store.shipping_relay').' :', $perks);
    }

    public function test_no_french_is_hardcoded_in_the_block(): void
    {
        $blade = (string) file_get_contents(resource_path('views/products/show.blade.php'));
        $perks = substr($blade, strpos($blade, 'product-detail-perks'));
        $perks = substr($perks, 0, strpos($perks, '</ul>'));

        // Les libellés passent par les traductions : « À domicile » écrit en
        // dur cassait la page le jour où une seconde langue arrive.
        $this->assertStringNotContainsString('À domicile', $perks);
        $this->assertStringNotContainsString('Point relais', $perks);
    }

    public function test_the_description_opens_on_a_lede(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('.product-detail-text > p:first-child', $css);
    }
}
