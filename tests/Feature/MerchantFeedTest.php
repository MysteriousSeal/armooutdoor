<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Google Merchant feed: every active product as an RSS item in the g:
 * vocabulary, priced as the page prices it, honest about identifiers.
 */
class MerchantFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_feed_lists_an_active_product_with_its_essentials(): void
    {
        $product = Product::factory()->create([
            'is_active' => true, 'quantity' => 10, 'sku' => 'CIB-FLUO-2020',
            'price_cents' => 1290, 'gtin' => '3701234567890', 'brand' => 'Armo',
        ]);

        $response = $this->get('/feed/google.xml')->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();

        $this->assertStringContainsString('xmlns:g="http://base.google.com/ns/1.0"', $xml);
        $this->assertStringContainsString('<g:id>CIB-FLUO-2020</g:id>', $xml);
        $this->assertStringContainsString('<g:price>12.90 EUR</g:price>', $xml);
        $this->assertStringContainsString('<g:availability>in_stock</g:availability>', $xml);
        $this->assertStringContainsString('<g:condition>new</g:condition>', $xml);
        $this->assertStringContainsString('<g:gtin>3701234567890</g:gtin>', $xml);
        $this->assertStringContainsString('<g:brand>Armo</g:brand>', $xml);
        $this->assertStringContainsString('/products/'.$product->slug, $xml);
        // Codes stated, so no disclaimer.
        $this->assertStringNotContainsString('identifier_exists', $xml);
    }

    public function test_the_gallery_rides_along_as_additional_images(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5, 'image' => 'products/main.webp']);

        foreach (['products/a.webp', 'products/b.webp'] as $i => $file) {
            ProductImage::query()->create(['product_id' => $product->id, 'image' => $file, 'sort_order' => $i]);
        }

        $xml = $this->get('/feed/google.xml')->assertOk()->getContent();

        // One main image, and the gallery beside it: g:image_link holds one
        // and only one, Merchant Center takes the rest in its own tag.
        $this->assertSame(1, substr_count($xml, '<g:image_link>'));
        $this->assertSame(2, substr_count($xml, '<g:additional_image_link>'));
        $this->assertStringContainsString('products/a.webp</g:additional_image_link>', $xml);
        $this->assertStringContainsString('products/b.webp</g:additional_image_link>', $xml);
    }

    public function test_a_product_without_a_gallery_adds_nothing(): void
    {
        Product::factory()->create(['is_active' => true, 'quantity' => 5]);

        $xml = $this->get('/feed/google.xml')->assertOk()->getContent();

        $this->assertSame(1, substr_count($xml, '<g:image_link>'));
        $this->assertStringNotContainsString('additional_image_link', $xml);
    }

    public function test_the_main_image_is_not_repeated_and_ten_is_the_ceiling(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5, 'image' => 'products/main.webp']);

        // The same file as the main image, then twelve others.
        ProductImage::query()->create(['product_id' => $product->id, 'image' => 'products/main.webp', 'sort_order' => 0]);

        for ($i = 1; $i <= 12; $i++) {
            ProductImage::query()->create(['product_id' => $product->id, 'image' => 'products/g'.$i.'.webp', 'sort_order' => $i]);
        }

        $xml = $this->get('/feed/google.xml')->assertOk()->getContent();

        // Repeating the main photograph would spend a slot on a picture the
        // shopper has already seen, and Merchant Center takes ten.
        $this->assertSame(10, substr_count($xml, '<g:additional_image_link>'));
        $this->assertSame(1, substr_count($xml, 'products/main.webp'));
    }

    public function test_a_weighed_product_states_its_shipping_weight(): void
    {
        Product::factory()->create(['is_active' => true, 'quantity' => 5, 'sku' => 'HEAVY', 'weight_grams' => 250]);
        Product::factory()->create(['is_active' => true, 'quantity' => 5, 'sku' => 'UNWEIGHED', 'weight_grams' => null]);

        $xml = $this->get('/feed/google.xml')->getContent();

        $this->assertMatchesRegularExpression('#<g:id>HEAVY</g:id>.*?<g:shipping_weight>250 g</g:shipping_weight>#s', $xml);
        $this->assertMatchesRegularExpression('#<g:id>UNWEIGHED</g:id>(?:(?!shipping_weight).)*?</item>#s', $xml);
    }

    public function test_an_inactive_product_stays_out(): void
    {
        Product::factory()->create(['is_active' => false, 'sku' => 'HIDDEN-SKU']);

        $this->assertStringNotContainsString('HIDDEN-SKU', $this->get('/feed/google.xml')->getContent());
    }

    public function test_a_product_without_codes_says_so(): void
    {
        Product::factory()->create([
            'is_active' => true, 'quantity' => 5, 'sku' => 'NO-CODES',
            'gtin' => null, 'brand' => null,
        ]);

        $this->assertStringContainsString(
            '<g:identifier_exists>no</g:identifier_exists>',
            $this->get('/feed/google.xml')->getContent(),
        );
    }

    public function test_availability_speaks_googles_three_words(): void
    {
        Product::factory()->create([
            'is_active' => true, 'sku' => 'GONE', 'quantity' => 0,
            'available_at_supplier' => false,
        ]);
        Product::factory()->create([
            'is_active' => true, 'sku' => 'AT-SUPPLIER', 'quantity' => 0,
            // Backorderable means a supplier really holds it.
            'available_at_supplier' => true,
            'supplier_id' => Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5])->id,
        ]);

        $xml = $this->get('/feed/google.xml')->getContent();

        $this->assertMatchesRegularExpression('#<g:id>GONE</g:id>.*?<g:availability>out_of_stock</g:availability>#s', $xml);
        $this->assertMatchesRegularExpression('#<g:id>AT-SUPPLIER</g:id>.*?<g:availability>backorder</g:availability>#s', $xml);
    }

    public function test_a_category_switched_out_keeps_its_products_home(): void
    {
        $out = Category::factory()->create(['google_feed' => false]);
        Product::factory()->create(['is_active' => true, 'quantity' => 5, 'sku' => 'KEPT-HOME', 'category_id' => $out->id]);
        Product::factory()->create(['is_active' => true, 'quantity' => 5, 'sku' => 'STILL-FED']);

        $xml = $this->get('/feed/google.xml')->getContent();

        $this->assertStringNotContainsString('KEPT-HOME', $xml);
        $this->assertStringContainsString('STILL-FED', $xml);
    }

    public function test_a_parent_switched_out_takes_its_subcategories_along(): void
    {
        $parent = Category::factory()->create(['google_feed' => false]);
        $child = Category::factory()->create(['google_feed' => true, 'parent_id' => $parent->id]);
        Product::factory()->create(['is_active' => true, 'quantity' => 5, 'sku' => 'CHILD-SKU', 'category_id' => $child->id]);

        $this->assertStringNotContainsString('CHILD-SKU', $this->get('/feed/google.xml')->getContent());
    }

    public function test_an_admin_flips_the_switch_from_the_categories_page(): void
    {
        $category = Category::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.google-feed', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->google_feed);

        $this->actingAs($admin)->patch(route('admin.categories.google-feed', $category));

        $this->assertTrue($category->fresh()->google_feed);
    }

    public function test_the_switch_answers_json_for_the_reloadless_pill(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('admin.categories.google-feed', $category))
            ->assertOk()
            ->assertExactJson(['google_feed' => false]);
    }

    public function test_the_xml_survives_an_ampersand_in_a_name(): void
    {
        Product::factory()->create([
            'is_active' => true, 'quantity' => 5, 'sku' => 'AMP',
            'name' => ['fr' => 'Cibles & planches', 'en' => 'Targets & boards'],
        ]);

        $xml = $this->get('/feed/google.xml')->assertOk()->getContent();

        $this->assertStringContainsString('Cibles &amp; planches', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }
}
