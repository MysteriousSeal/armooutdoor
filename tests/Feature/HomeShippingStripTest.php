<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\ShippingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The free-shipping strip above the site header. It is the first thing on the
 * homepage, so it only appears when there is a real figure behind it — and it
 * appears nowhere else.
 */
class HomeShippingStripTest extends TestCase
{
    use RefreshDatabase;

    private function carrier(): Carrier
    {
        return Carrier::query()->create([
            'slug' => 'strip-carrier',
            'name' => ['en' => 'Carrier', 'fr' => 'Transporteur'],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => 'home',
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function freeShippingOver(?int $cents, bool $withCarrier = true): void
    {
        $setting = ShippingSetting::current();
        $setting->free_shipping_threshold_cents = $cents;
        $setting->free_shipping_carrier_ids = $withCarrier ? [$this->carrier()->id] : [];
        $setting->save();
    }

    public function test_the_strip_names_the_threshold(): void
    {
        $this->freeShippingOver(6000);

        $this->get('/')
            ->assertOk()
            ->assertSee('ship-strip', false)
            ->assertSee(__('store.home_ship_strip', ['amount' => '60€']), false);
    }

    public function test_the_strip_sits_above_the_site_header(): void
    {
        $this->freeShippingOver(6000);

        $html = $this->get('/')->assertOk()->getContent();

        // "Very top" means above the logo and nav, not merely near them.
        $this->assertLessThan(
            strpos($html, '<header class="site-header">'),
            strpos($html, 'class="ship-strip"'),
        );
    }

    public function test_no_threshold_means_no_strip(): void
    {
        $this->freeShippingOver(null);

        $this->get('/')->assertOk()->assertDontSee('ship-strip', false);
    }

    public function test_a_threshold_with_no_carrier_behind_it_promises_nothing(): void
    {
        // Free shipping is granted per carrier. A threshold with none flagged
        // would advertise a discount that checkout never applies.
        $this->freeShippingOver(6000, withCarrier: false);

        $this->get('/')->assertOk()->assertDontSee('ship-strip', false);
    }

    public function test_the_strip_is_only_on_the_homepage(): void
    {
        $this->freeShippingOver(6000);

        foreach (['/contact', '/nouveautes', '/meilleures-ventes'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('ship-strip', false);
        }
    }
}
