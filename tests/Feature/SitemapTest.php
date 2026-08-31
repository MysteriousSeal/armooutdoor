<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_blog_sitemap_writes_w3c_lastmod_dates(): void
    {
        // Les entrées « max(updated_at) » (index du blog, rubriques) rendaient
        // la chaîne brute de la base — « 2026-08-31 11:21:13 » — que Search
        // Console rejette comme date invalide.
        BlogPost::factory()->create();

        $xml = $this->get('/sitemap-blog.xml')->assertOk()->getContent();

        preg_match_all('/<lastmod>([^<]+)<\/lastmod>/', $xml, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $lastmod) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
                $lastmod,
            );
        }
    }

    public function test_robots_txt_points_to_the_current_sitemap_url(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /cart')
            ->assertSee('Disallow: /checkout')
            ->assertSee('Sitemap: '.route('sitemap.index'));
    }
}
