<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's mark as the tab icon, on the storefront and the back office
 * alike, with the fallbacks the SVG cannot cover on its own.
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_icon_the_layouts_name_exists(): void
    {
        foreach (['favicon.svg', 'favicon-32.png', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path($file), $file.' is linked but missing');
        }
    }

    public function test_the_storefront_and_the_back_office_share_them(): void
    {
        $pages = [
            $this->get('/')->assertOk()->getContent(),
            $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboard')->assertOk()->getContent(),
        ];

        foreach ($pages as $html) {
            $this->assertStringContainsString('favicon.svg', $html);
            $this->assertStringContainsString('favicon-32.png', $html);
            $this->assertStringContainsString('apple-touch-icon', $html);
        }
    }

    public function test_the_svg_follows_the_tab_strip(): void
    {
        $svg = file_get_contents(public_path('favicon.svg'));

        // No ground, and an ink that flips: a dark A would otherwise vanish
        // into a dark tab strip.
        $this->assertStringContainsString('prefers-color-scheme: dark', $svg);
        $this->assertStringNotContainsString('#f7f6f4', $svg);
    }
}
