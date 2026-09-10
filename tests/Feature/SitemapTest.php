<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Product;
use App\Support\Guides;
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
            // Deliberately crawlable: their noindex header must be readable,
            // and a Disallow would leave the bare URLs stuck in the index.
            ->assertDontSee('Disallow: /cart')
            ->assertDontSee('Disallow: /checkout')
            ->assertSee('Sitemap: '.route('sitemap.index'));
    }

    public function test_the_guides_have_their_own_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-guides.xml', false);

        $xml = $this->get('/sitemap-guides.xml')->assertOk()->getContent();

        foreach ([
            route('guides.index'),
            route('guides.cibles'),
            route('guides.entretien'),
            route('guides.classification'),
        ] as $url) {
            $this->assertStringContainsString('<loc>'.$url.'</loc>', $xml);
        }

        $this->get('/sitemap-pages.xml')->assertOk()
            ->assertDontSee(route('guides.index'));

        $plan = $this->get('/plan-du-site')->assertOk()
            ->assertSee('id="sitemap-guides-heading"', false)
            ->assertSee('id="sitemap-pages-heading"', false)
            ->assertSee(__('store.sitemap_guides'))
            ->assertSee('Classer son arme')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<h2 class="sitemap-heading" id="sitemap-guides-heading">\s*Guides\s*<\/h2>/',
            $plan,
        );
        $this->assertStringNotContainsString("Guides d'achat", $plan);
        $this->assertStringNotContainsString('Guides d&#039;achat', $plan);
    }

    public function test_the_html_plan_lists_every_guide_on_the_shelf(): void
    {
        // The Guides section once had three guides typed into the view by
        // hand, so the shelf outgrew it silently: a 4th guide was never
        // linked from the one page meant to link everything. Reading off
        // Guides::all() here, the way the guide's own test reads it, means
        // an 11th guide is covered without anyone remembering to list it.
        $plan = $this->get('/plan-du-site')->assertOk()->getContent();

        foreach (Guides::all() as $guide) {
            $this->assertStringContainsString($guide['url'], $plan);
        }
    }

    public function test_the_contact_page_and_the_html_plan_are_listed(): void
    {
        $xml = $this->get('/sitemap-pages.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('contact.show').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('sitemap.html').'</loc>', $xml);
    }

    public function test_the_legal_pages_carry_the_date_they_show_a_visitor(): void
    {
        // The four legal pages state their own "last updated" date to a
        // reader, from config('shop.legal_updated'), and the sitemap wrote
        // no lastmod at all for any of the sixteen pages in this file: a
        // date already sitting on the page was never read into the one
        // place a crawler looks for it.
        $xml = $this->get('/sitemap-pages.xml')->assertOk()->getContent();

        foreach ([
            'legal.terms' => 'terms',
            'legal.notice' => 'notice',
            'legal.privacy' => 'privacy',
            'legal.withdrawal' => 'withdrawal',
        ] as $route => $key) {
            $this->assertStringContainsString(
                '<loc>'.route($route).'</loc>'."\n".'<lastmod>'.config('shop.legal_updated.'.$key).'</lastmod>',
                preg_replace('/[ \t]+/', '', $xml),
            );
        }
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

        foreach (['index', 'pages', 'categories', 'products', 'blog', 'guides'] as $name) {
            $url = $name === 'index' ? '/sitemap.xml' : '/sitemap-'.$name.'.xml';
            $xml = $this->get($url)->assertOk()->getContent();

            $this->assertNotFalse(
                simplexml_load_string($xml),
                $url.' is not well-formed XML.',
            );
        }
    }

    public function test_every_listed_page_still_answers(): void
    {
        // The six help and legal pages were restructured; a sitemap is the one
        // place a broken route is announced to Google rather than noticed.
        preg_match_all(
            '#<loc>([^<]+)</loc>#',
            $this->get('/sitemap-pages.xml')->assertOk()->getContent(),
            $matches,
        );

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $url) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';

            $this->get($path)->assertOk();
        }
    }
}
