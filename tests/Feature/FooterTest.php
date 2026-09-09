<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\MarketplaceSetting;
use App\Models\ShippingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer, which is the floor of every page.
 *
 * It repeats the free shipping promise and lists where else the shop sells,
 * so both are read from the settings rows rather than written into the
 * layout, and both disappear when there is nothing true to say.
 */
class FooterTest extends TestCase
{
    use RefreshDatabase;

    private function carrier(): Carrier
    {
        return Carrier::query()->create([
            'slug' => 'footer-carrier',
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

    public function test_the_footer_repeats_the_free_shipping_promise(): void
    {
        $this->freeShippingOver(4900);

        $this->get('/contact')->assertOk()
            ->assertSee('site-footer-promise', false)
            ->assertSee('<b>49€</b>', false);
    }

    public function test_a_threshold_with_no_carrier_behind_it_promises_nothing(): void
    {
        // Free shipping is granted per carrier, so a threshold with none
        // flagged would advertise a discount checkout never applies.
        $this->freeShippingOver(4900, withCarrier: false);

        $this->get('/contact')->assertOk()->assertDontSee('49€', false);
    }

    public function test_no_threshold_means_no_promise_in_the_footer(): void
    {
        $this->freeShippingOver(null);

        $this->get('/contact')->assertOk()->assertDontSee(__('store.footer_ship_note'), false);
    }

    /**
     * The home strip and the footer make the same claim on the same page, so
     * they read one rule rather than each carrying its own copy of it.
     */
    public function test_the_footer_and_the_home_strip_name_the_same_threshold(): void
    {
        $this->freeShippingOver(6000);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            ShippingSetting::current()->freeShippingLabel(),
            '60€',
        );
        $this->assertStringContainsString('<b class="ship-strip-amount">60€</b>', $html);
        $this->assertStringContainsString('<strong>Livraison offerte dès <b>60€</b></strong>', $html);
    }

    public function test_the_footer_links_to_the_shops_other_storefronts(): void
    {
        $settings = MarketplaceSetting::current();
        $settings->naturabuy_url = 'https://www.naturabuy.fr/stores/2811/';
        $settings->vinted_url = 'https://www.vinted.fr/member/1-armooutdoor';
        $settings->save();

        $this->get('/contact')->assertOk()
            ->assertSee('site-footer-elsewhere', false)
            ->assertSee('https://www.naturabuy.fr/stores/2811/', false)
            ->assertSee('https://www.vinted.fr/member/1-armooutdoor', false);
    }

    public function test_a_storefront_with_no_address_is_not_listed(): void
    {
        // A footer link to nothing is worse than no link.
        $settings = MarketplaceSetting::current();
        $settings->naturabuy_url = 'https://www.naturabuy.fr/stores/2811/';
        $settings->vinted_url = null;
        $settings->save();

        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('naturabuy.fr', $html);
        $this->assertStringNotContainsString('>Vinted<', $html);
    }

    public function test_the_reading_links_moved_in_with_the_help_ones(): void
    {
        // Two links in a column of their own left the grid ragged, and both
        // errands are the same one: looking something up rather than buying.
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'class="site-footer-col"'));
        $this->assertStringNotContainsString('footer-reading-heading', $html);
        $this->assertStringContainsString(__('store.footer_reading_guides'), $html);
        $this->assertStringContainsString(__('store.footer_reading_blog'), $html);
    }

    /**
     * The three promises are the same three the strip under the hero makes.
     * They are worth restating where somebody is deciding whether to leave,
     * and worth checking they all arrive: one of them is conditional.
     */
    public function test_the_footer_carries_three_promises(): void
    {
        $this->freeShippingOver(4900);

        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'class="site-footer-promise"'));
        $this->assertStringContainsString(__('store.home_hero_pay_title'), $html);
        $this->assertStringContainsString(__('store.footer_returns_title'), $html);
    }

    public function test_the_promises_that_do_not_depend_on_a_setting_always_show(): void
    {
        // Payment and returns are true whatever the shipping settings say, so
        // the row loses one cell rather than the whole band.
        $this->freeShippingOver(null);

        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="site-footer-promise"'));
        $this->assertStringContainsString(__('store.home_hero_pay_title'), $html);
    }

    /**
     * The footer is the floor of the page and says so with an olive rule
     * across its top, which is the one thing separating it from the page
     * above rather than more of it.
     */
    public function test_the_footer_opens_on_an_olive_rule(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $start = strpos($css, '.site-footer {');
        $rule = substr($css, $start, strpos($css, '}', $start) - $start);

        $this->assertMatchesRegularExpression(
            '/border-top:\s*2px solid var\(--accent-heading\)\s*;/',
            $rule,
        );
    }
}
