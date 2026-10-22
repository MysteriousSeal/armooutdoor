<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The welcome-code band under the free-shipping strip. It reads its amount
 * and its code from the discount code ticked for it in the back office, and
 * it never advertises a code checkout would refuse.
 */
class HomeWelcomeStripTest extends TestCase
{
    use RefreshDatabase;

    private function welcomeCode(array $overrides = []): DiscountCode
    {
        return DiscountCode::query()->create([
            'code' => 'BIENVENUE10',
            'type' => DiscountCode::TYPE_PERCENTAGE,
            'value' => 10,
            'max_uses_per_customer' => 1,
            'in_welcome_banner' => true,
            ...$overrides,
        ]);
    }

    private function orderWith(DiscountCode $code, User $customer): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'Jean', 'last_name' => 'Client', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'Jean', 'last_name' => 'Client', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 100,
            'total_cents' => 900,
            'payment_method' => 'card',
            'discount_code_id' => $code->id,
        ]);
    }

    public function test_the_band_shows_the_codes_own_amount_and_name(): void
    {
        $this->welcomeCode(['value' => 15]);

        $this->get('/')
            ->assertOk()
            ->assertSee('ship-strip--welcome', false)
            ->assertSee('<b class="ship-strip-amount">-15%</b> sur votre première commande', false)
            ->assertSee('<code class="welcome-strip-code">BIENVENUE10</code>', false);
    }

    public function test_a_fixed_amount_code_shows_in_euros(): void
    {
        $this->welcomeCode(['type' => DiscountCode::TYPE_FIXED, 'value' => 500]);

        $this->get('/')->assertSee('<b class="ship-strip-amount">-'.format_euros(500).'</b>', false);
    }

    /** The rust ground has to carry the same light ink as the olive one. */
    public function test_the_band_keeps_its_text_readable_in_both_themes(): void
    {
        $css = file_get_contents(__DIR__.'/../../public/css/top-strips.css');

        preg_match_all('/--welcome-band:\s*(#[0-9a-f]{6})/i', $css, $bands);
        preg_match_all('/--ship-ink:\s*(#[0-9a-f]{6})/i', $css, $inks);

        $this->assertCount(2, $bands[1]);

        foreach ($bands[1] as $index => $band) {
            $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($band, $inks[1][$index]), "Contrast on {$band} below AA");
        }
    }

    public function test_no_band_without_a_ticked_code(): void
    {
        $this->welcomeCode(['in_welcome_banner' => false]);

        $this->get('/')->assertOk()->assertDontSee('ship-strip--welcome', false);
    }

    public function test_no_band_once_the_code_has_expired_or_run_out(): void
    {
        $code = $this->welcomeCode(['ends_at' => now()->subMinute()]);
        $this->get('/')->assertDontSee('ship-strip--welcome', false);

        $code->update(['ends_at' => null, 'quantity' => 0]);
        $this->get('/')->assertDontSee('ship-strip--welcome', false);
    }

    public function test_a_customer_who_has_used_the_code_no_longer_sees_it(): void
    {
        $code = $this->welcomeCode();
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/')->assertSee('ship-strip--welcome', false);

        $this->orderWith($code, $customer);

        $this->actingAs($customer)->get('/')->assertDontSee('ship-strip--welcome', false);
    }

    public function test_the_band_sits_above_the_site_header(): void
    {
        $this->welcomeCode();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, '<header class="site-header">'), strpos($html, 'ship-strip--welcome'));
    }

    /** Both strips follow the visitor into the catalogue, with their stylesheet. */
    public function test_both_strips_show_across_the_catalogue_pages(): void
    {
        $this->welcomeCode();
        $setting = ShippingSetting::current();
        $setting->free_shipping_threshold_cents = 4900;
        $setting->free_shipping_carrier_ids = [Carrier::query()->create([
            'slug' => 'strip-carrier',
            'name' => ['en' => 'Carrier', 'fr' => 'Transporteur'],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => 'home',
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ])->id];
        $setting->save();

        $category = Category::factory()->create(['parent_id' => null]);
        $product = Product::factory()->create(['is_active' => true, 'category_id' => $category->id]);

        foreach (['/categories', '/categories/'.$category->slug, '/products/'.$product->slug, '/nouveautes', '/promotions', '/meilleures-ventes', '/cart'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('class="ship-strip"', false)
                ->assertSee('ship-strip--welcome', false)
                ->assertSee('css/top-strips.css', false);
        }

        $this->get('/faq')->assertDontSee('ship-strip', false)->assertDontSee('top-strips.css', false);
    }

    public function test_ticking_a_code_for_the_banner_unticks_the_previous_one(): void
    {
        $old = $this->welcomeCode(['code' => 'OLDWELCOME']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/discount-codes', [
                'code' => 'BIENVENUE10',
                'type' => DiscountCode::TYPE_PERCENTAGE,
                'value' => '10',
                'max_uses_per_customer' => '1',
                'in_welcome_banner' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($old->fresh()->in_welcome_banner);
        $this->assertTrue(DiscountCode::query()->where('code', 'BIENVENUE10')->first()->in_welcome_banner);
    }

    public function test_the_banner_refuses_a_customer_only_or_free_delivery_code(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/discount-codes', [
                'code' => 'JUSTFORONE',
                'type' => DiscountCode::TYPE_PERCENTAGE,
                'value' => '10',
                'user_id' => User::factory()->create()->id,
                'in_welcome_banner' => '1',
            ])
            ->assertSessionHasErrors('in_welcome_banner');

        $this->actingAs($admin)
            ->post('/admin/discount-codes', [
                'code' => 'FREERELAY',
                'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
                'in_welcome_banner' => '1',
            ])
            ->assertSessionHasErrors('in_welcome_banner');
    }

    private function contrastRatio(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            [$r, $g, $b] = array_map(fn (string $pair): float => (int) hexdec($pair) / 255, str_split(ltrim($hex, '#'), 2));
            $channel = fn (float $value): float => $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
        };

        return (max($luminance($a), $luminance($b)) + 0.05) / (min($luminance($a), $luminance($b)) + 0.05);
    }
}
