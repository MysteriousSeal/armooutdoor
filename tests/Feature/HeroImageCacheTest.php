<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The photographs the shop ships itself, stamped like its stylesheets.
 *
 * They keep their filenames when they are replaced, so without a stamp a
 * visitor who has been here before keeps the old picture until they clear
 * their cache, which nobody does.
 */
class HeroImageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_photographs_are_stamped(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['hero.webp', 'hero-2.webp', 'hero-3.webp', 'hero-4.webp', 'about.webp'] as $file) {
            $this->assertMatchesRegularExpression(
                '#images/'.preg_quote($file, '#').'\?v=\d+#',
                $html,
                $file.' is served without a stamp, so a replacement will not reach anyone who has been here.'
            );
        }
    }

    public function test_the_preload_names_the_same_url_the_panel_paints(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('#<link\s+rel="preload"\s+as="image"\s+href="([^"]+)"#s', $html, $preload);
        preg_match("#--hero-image: url\('([^']+)'\)#", $html, $panel);

        $this->assertNotEmpty($preload, 'The first panel is no longer preloaded.');
        $this->assertNotEmpty($panel, 'No panel is painting a photograph.');

        // A preload that does not match the URL the panel paints fetches the
        // photograph twice, which is the opposite of what it is there for.
        $this->assertSame($preload[1], $panel[1]);
    }

    public function test_a_page_without_its_own_picture_shares_the_stamped_one(): void
    {
        // The card a link brings with it: an unstamped URL leaves the old
        // photograph on every post and message already shared.
        $html = $this->get('/confidentialite')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#property="og:image" content="[^"]*images/hero\.webp\?v=\d+"#',
            $html
        );
    }
}
