<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    // --- Search --------------------------------------------------------

    public function test_search_with_an_empty_query_shows_no_results(): void
    {
        $this->get('/search')
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_search_finds_a_product_by_name(): void
    {
        $this->get('/search?'.http_build_query(['q' => 'Tente']))
            ->assertOk()
            ->assertSee('Tente crête deux places');
    }

    public function test_search_with_no_matches_returns_empty_results(): void
    {
        $this->get('/search?q=zzz-nonexistent-zzz')
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_search_ignores_special_characters_without_erroring(): void
    {
        $this->get('/search?q='.urlencode('%_<script>'))
            ->assertOk();
    }

    public function test_search_excludes_inactive_products(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $product->update(['is_active' => false]);

        $this->get('/search?q=Tente')
            ->assertOk()
            ->assertDontSee('Tente crête deux places');
    }

    // --- New arrivals / best sellers / promotions -----------------------

    public function test_new_arrivals_lists_active_products(): void
    {
        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('Tente crête deux places');
    }

    public function test_new_arrivals_excludes_inactive_products(): void
    {
        Product::query()->update(['is_active' => false]);

        $this->get('/nouveautes')
            ->assertOk()
            ->assertDontSee('Tente crête deux places');
    }

    public function test_promotions_only_lists_products_with_an_active_discount(): void
    {
        $discounted = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $notDiscounted = Product::query()->where('slug', 'daylight-pack')->firstOrFail();

        Discount::query()->create([
            'product_id' => $discounted->id,
            'type' => 'percentage',
            'value' => 10,
        ]);

        $this->get('/promotions')
            ->assertOk()
            ->assertSee($discounted->localizedName())
            ->assertDontSee($notDiscounted->localizedName());
    }

    public function test_promotions_page_shows_empty_state_with_no_discounts(): void
    {
        $this->get('/promotions')->assertOk();
    }

    public function test_best_sellers_page_loads_with_no_orders_yet(): void
    {
        $this->get('/meilleures-ventes')->assertOk();
    }

    // --- FAQ / help / legal ---------------------------------------------

    public function test_faq_page_is_available(): void
    {
        $this->get('/faq')->assertOk();
    }

    public function test_shipping_and_returns_help_page_is_available(): void
    {
        $this->get('/livraison-et-retours')->assertOk();
    }

    public function test_secure_payment_help_page_is_available(): void
    {
        $this->get('/paiement-securise')->assertOk();
    }

    public function test_legal_pages_are_available(): void
    {
        foreach (['/cgv', '/mentions-legales', '/confidentialite', '/droit-de-retractation'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    // --- Sitemaps --------------------------------------------------------

    public function test_sitemap_index_is_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }

    public function test_sitemap_pages_is_valid_xml(): void
    {
        $response = $this->get('/sitemap-pages.xml');

        $response->assertOk();
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }

    public function test_sitemap_categories_is_valid_xml(): void
    {
        $response = $this->get('/sitemap-categories.xml');

        $response->assertOk();
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }

    public function test_sitemap_products_excludes_inactive_products(): void
    {
        $inactive = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $inactive->update(['is_active' => false]);

        $response = $this->get('/sitemap-products.xml');

        $response->assertOk();
        $this->assertStringNotContainsString('ridge-tent', $response->getContent());
        $this->assertStringContainsString('daylight-pack', $response->getContent());
    }

    public function test_html_sitemap_is_available(): void
    {
        $this->get('/plan-du-site')->assertOk();
    }

    // --- Theme preference --------------------------------------------------

    public function test_theme_preference_accepts_light_and_dark(): void
    {
        $this->postJson('/preferences/theme', ['theme' => 'dark'])
            ->assertOk()
            ->assertJson(['theme' => 'dark'])
            ->assertCookie('theme', 'dark');

        $this->postJson('/preferences/theme', ['theme' => 'light'])
            ->assertOk()
            ->assertJson(['theme' => 'light']);
    }

    public function test_theme_preference_rejects_invalid_values(): void
    {
        $this->postJson('/preferences/theme', ['theme' => 'purple'])
            ->assertStatus(422);
    }

    public function test_theme_preference_does_not_require_authentication(): void
    {
        $this->postJson('/preferences/theme', ['theme' => 'dark'])->assertOk();
    }

    // --- Unknown slugs -----------------------------------------------------

    public function test_unknown_product_slug_is_not_found(): void
    {
        $this->get('/products/does-not-exist')->assertNotFound();
    }

    public function test_unknown_category_slug_is_not_found(): void
    {
        $this->get('/categories/does-not-exist')->assertNotFound();
    }

    public function test_inactive_product_page_is_not_found(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $product->update(['is_active' => false]);

        $this->get('/products/'.$product->slug)->assertNotFound();
    }
}
