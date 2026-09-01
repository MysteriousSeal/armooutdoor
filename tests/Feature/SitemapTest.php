<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Product;
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

    public function test_the_contact_page_and_the_html_plan_are_listed(): void
    {
        $xml = $this->get('/sitemap-pages.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('contact.show').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('sitemap.html').'</loc>', $xml);
    }

    public function test_a_product_carries_its_photographs_into_the_sitemap(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $xml = $this->get('/sitemap-products.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $xml);
        $this->assertStringContainsString('<image:loc>'.$product->imageUrl().'</image:loc>', $xml);
    }

    public function test_every_sitemap_is_well_formed_xml(): void
    {
        Product::factory()->create(['is_active' => true]);

        foreach (['index', 'pages', 'categories', 'products', 'blog'] as $name) {
            $url = $name === 'index' ? '/sitemap.xml' : '/sitemap-'.$name.'.xml';
            $xml = $this->get($url)->assertOk()->getContent();

            $this->assertNotFalse(
                simplexml_load_string($xml),
                $url.' is not well-formed XML.',
            );
        }
    }
}
