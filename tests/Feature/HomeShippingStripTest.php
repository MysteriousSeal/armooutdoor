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

    public function test_the_amount_is_stamped_inside_the_sentence(): void
    {
        // La phrase est interpolée en HTML brut pour porter le tampon : si
        // l'échappement revenait, la balise s'afficherait telle quelle.
        $this->freeShippingOver(4900);

        $this->get('/')->assertOk()
            ->assertSee('<b class="ship-strip-amount">49€</b>', false)
            ->assertDontSee('&lt;b class=', false);
    }

    /**
     * La bande est pleine : son encre doit tenir dessus. Un olive éclairci
     * un jour de retouche ferait passer la seule phrase que la page doit
     * faire lire sous le seuil de lisibilité, sans que rien ne casse.
     */
    public function test_the_band_keeps_its_text_readable_in_both_themes(): void
    {
        $css = file_get_contents(__DIR__.'/../../public/css/home.css');

        foreach (['--ship-band', '--ship-ink'] as $token) {
            $this->assertMatchesRegularExpression(
                '/'.$token.':\s*#[0-9a-f]{6}/i',
                $css,
                "{$token} must be declared as a hex colour"
            );
        }

        preg_match_all('/--ship-band:\s*(#[0-9a-f]{6})/i', $css, $bands);
        preg_match_all('/--ship-ink:\s*(#[0-9a-f]{6})/i', $css, $inks);

        // Un jeu par thème : clair et sombre.
        $this->assertCount(2, $bands[1]);
        $this->assertCount(2, $inks[1]);

        foreach ($bands[1] as $index => $band) {
            $ratio = $this->contrastRatio($band, $inks[1][$index]);

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                "Contrast of {$inks[1][$index]} on {$band} is ".round($ratio, 2).':1, below AA'
            );
        }
    }

    private function contrastRatio(string $a, string $b): float
    {
        $lighter = max($this->relativeLuminance($a), $this->relativeLuminance($b));
        $darker = min($this->relativeLuminance($a), $this->relativeLuminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn (string $pair): float => (int) hexdec($pair) / 255,
            str_split(ltrim($hex, '#'), 2),
        );

        $channel = fn (float $value): float => $value <= 0.03928
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }
}
