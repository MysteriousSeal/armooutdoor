<?php

namespace Tests\Feature;

use App\Models\Product;
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
            'supplier_id' => \App\Models\Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5])->id,
        ]);

        $xml = $this->get('/feed/google.xml')->getContent();

        $this->assertMatchesRegularExpression('#<g:id>GONE</g:id>.*?<g:availability>out_of_stock</g:availability>#s', $xml);
        $this->assertMatchesRegularExpression('#<g:id>AT-SUPPLIER</g:id>.*?<g:availability>backorder</g:availability>#s', $xml);
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
