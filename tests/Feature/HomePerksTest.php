<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\ShippingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two offers under the catalogue band: each figure comes from its setting
 * or its code, and a panel with nothing true to say does not show.
 */
class HomePerksTest extends TestCase
{
    use RefreshDatabase;

    private function freeShippingOver(int $cents): void
    {
        $carrier = Carrier::query()->create([
            'slug' => 'colissimo',
            'name' => ['en' => 'Colissimo', 'fr' => 'Colissimo'],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => 'home',
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ]);

        $setting = ShippingSetting::current();
        $setting->free_shipping_threshold_cents = $cents;
        $setting->free_shipping_carrier_ids = [$carrier->id];
        $setting->save();
    }

    private function welcomeCode(int $value = 10): DiscountCode
    {
        return DiscountCode::query()->create([
            'code' => 'BIENVENUE10',
            'type' => DiscountCode::TYPE_PERCENTAGE,
            'value' => $value,
            'max_uses_per_customer' => 1,
            'in_welcome_banner' => true,
        ]);
    }

    public function test_both_offers_read_their_own_figures(): void
    {
        $this->freeShippingOver(4900);
        $this->welcomeCode(15);

        $this->get('/')
            ->assertOk()
            ->assertSee('<span class="home-perk-count">49€</span>', false)
            ->assertSee('France métropolitaine')
            ->assertSee('<span class="home-perk-count">-15%</span>', false)
            ->assertSee('sur votre première commande')
            ->assertSee('<code class="home-perk-code">BIENVENUE10</code>', false);
    }

    public function test_the_offers_sit_under_the_aisles(): void
    {
        $this->freeShippingOver(4900);
        Product::factory()->create(['is_active' => true]);

        $html = $this->get('/')->assertOk()->getContent();
        $aisles = strpos($html, '<section class="home-cats-section"');

        $this->assertNotFalse($aisles);
        $this->assertGreaterThan($aisles, strpos($html, '<section class="home-perks"'));
    }

    public function test_only_the_offer_that_holds_shows(): void
    {
        $this->welcomeCode();

        $this->get('/')
            ->assertSee('home-perk-code', false)
            ->assertDontSee('home-perk-more', false);
    }

    public function test_no_offer_no_block(): void
    {
        $this->get('/')->assertOk()->assertDontSee('home-perks', false);
    }
}
