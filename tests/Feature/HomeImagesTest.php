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
