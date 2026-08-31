<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's own photographs — the four carousel panels and the about
 * section — must point at files that are actually there.
 *
 * Renaming them is the moment this breaks: converting the set to WebP meant
 * touching five references in one file, and a missed one is a hole in the
 * page that nothing else in the suite would have noticed.
 */
class HomeImagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every homepage photograph, as a path relative to public/.
     *
     * @return list<string>
     */
    private function imagePaths(): array
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#(?:url\(\W*|src=")([^"\')]*images/[^"\')?]+)#', $html, $matches);

        return array_values(array_unique(array_map(
            fn (string $url) => ltrim(parse_url($url, PHP_URL_PATH) ?? $url, '/'),
            $matches[1]
        )));
    }

    public function test_every_homepage_image_exists_on_disk(): void
    {
        $paths = $this->imagePaths();

        $this->assertNotEmpty($paths, 'la page d\'accueil ne référence aucune image');

        foreach ($paths as $path) {
            $this->assertFileExists(public_path($path), $path.' est référencée mais absente');
        }
    }

    public function test_the_first_panel_photo_is_preloaded_at_high_priority(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('#<link[^>]*rel="preload"[^>]*as="image"[^>]*>#s', $html, $preload);

        $this->assertNotEmpty($preload, 'la photo du premier panneau n\'est pas préchargée');
        $this->assertStringContainsString('fetchpriority="high"', $preload[0]);
    }

    public function test_the_preload_matches_the_url_the_panel_paints(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('#<link[^>]*rel="preload"[^>]*as="image"[^>]*href="([^"]+)"#s', $html, $preload);
        preg_match('#--hero-image:\s*url\(\W*([^\)\x27"]+)#', $html, $panel);

        // A preload whose URL differs by so much as a query string fetches the
        // photograph a second time: slower than not preloading at all.
        $this->assertSame($panel[1], $preload[1]);
    }

    public function test_only_the_visible_panel_is_preloaded(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The other three are off screen; preloading them would spend the
        // visitor's bandwidth on pictures they may never reach.
        $this->assertSame(1, preg_match_all('#rel="preload"[^>]*as="image"#', $html));
    }

    public function test_the_four_panels_and_the_about_photo_are_all_there(): void
    {
        $paths = $this->imagePaths();

        // Counted, not just existing: a reference silently dropped would
        // leave the remaining ones passing the check above.
        $heroes = array_filter($paths, fn (string $p) => str_contains($p, 'images/hero'));

        $this->assertCount(4, $heroes);
        $this->assertContains('images/about.webp', $paths);
    }
}
