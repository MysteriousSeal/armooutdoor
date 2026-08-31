<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No stylesheet and no script may leave the site without a cache-busting
 * stamp. Scripts went unstamped for a long time: the CSS was rebuilt in every
 * visitor's browser on release while the JavaScript beside it stayed stale.
 */
class AssetCacheBustingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every local .css / .js URL in the markup, stamped or not.
     *
     * @return list<string>
     */
    private function localAssets(string $html): array
    {
        preg_match_all('/(?:href|src)="([^"]+\.(?:css|js)(?:\?[^"]*)?)"/i', $html, $matches);

        // Anything served from another origin is not ours to stamp.
        return array_values(array_filter(
            $matches[1],
            fn (string $url) => ! preg_match('#^(https?:)?//(?!127\.0\.0\.1|localhost)#i', $url)
        ));
    }

    private function assertEveryAssetIsStamped(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();
        $assets = $this->localAssets($html);

        $this->assertNotEmpty($assets, $url.' ne charge aucun asset : le test ne prouve rien');

        foreach ($assets as $asset) {
            $this->assertStringContainsString(
                '?v=',
                $asset,
                $asset.' part sans marqueur de version depuis '.$url
            );
        }
    }

    public function test_the_homepage_stamps_every_asset(): void
    {
        $this->assertEveryAssetIsStamped('/');
    }

    public function test_the_contact_page_stamps_every_asset(): void
    {
        // A page with its own script on top of the layout's.
        $this->assertEveryAssetIsStamped(localized_route('contact.show'));
    }

    public function test_the_scripts_are_stamped_too_not_just_the_stylesheets(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/src="([^"]+\.js(?:\?[^"]*)?)"/i', $html, $scripts);

        $this->assertNotEmpty($scripts[1], 'la page d\'accueil doit charger des scripts');

        foreach ($scripts[1] as $script) {
            $this->assertStringContainsString('?v=', $script, $script.' n\'est pas versionné');
        }
    }

    public function test_two_assets_do_not_share_one_stamp(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/\?v=([^"&]+)"/', $html, $stamps);
        $unique = array_unique($stamps[1]);

        // Per-file stamps: one shared value would mean the whole site busts
        // at once, which is the behaviour this replaced.
        $this->assertGreaterThan(1, count($unique), 'les assets partagent tous le même marqueur');
    }
}
