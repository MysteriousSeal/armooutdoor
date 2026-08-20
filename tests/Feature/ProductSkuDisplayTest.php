<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reference shown on a product page is the one for what actually goes in
 * the cart: the variant's when there is one, the product's otherwise, and
 * nothing at all when neither has a reference to give.
 */
class ProductSkuDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_reference_is_shown(): void
    {
        $product = Product::factory()->create(['sku' => 'CRT-REACT-076-200-FLUO']);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee(__('store.product_sku'))
            ->assertSee('id="product-detail-sku-value">CRT-REACT-076-200-FLUO</span>', false);
    }

    public function test_no_reference_means_no_line(): void
    {
        $product = Product::factory()->create(['sku' => null]);

        // A label with nothing after it is worse than no label: it reads as
        // missing data rather than as a product without a reference.
        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="product-detail-sku"\s+hidden/', $html);
        $this->assertStringContainsString('id="product-detail-sku-value"></span>', $html);
    }

    public function test_a_variant_reference_wins_over_the_products(): void
    {
        $product = Product::factory()->create(['sku' => 'PARENT-SKU']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => 'M',
            'sku' => 'VARIANT-SKU-M',
            'quantity' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // The variant is what the customer orders, so its reference is the
        // one that has to be on screen.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('id="product-detail-sku-value">VARIANT-SKU-M</span>', false);
    }

    public function test_a_variant_without_its_own_reference_falls_back_to_the_product(): void
    {
        $product = Product::factory()->create(['sku' => 'PARENT-SKU']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => 'M',
            'sku' => null,
            'quantity' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('id="product-detail-sku-value">PARENT-SKU</span>', false);
    }

    public function test_every_variant_carries_its_reference_for_the_script(): void
    {
        $product = Product::factory()->create(['sku' => 'PARENT-SKU']);

        foreach ([['S', 'SKU-S'], ['M', null]] as $i => [$label, $sku]) {
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'label' => $label,
                'sku' => $sku,
                'quantity' => 5,
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }

        // The line switches with the selection client-side, so each radio has
        // to carry its own answer — including the fallback.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('data-variant-sku="SKU-S"', false)
            ->assertSee('data-variant-sku="PARENT-SKU"', false);
    }
}
