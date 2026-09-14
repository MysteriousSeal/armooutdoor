<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reduction badge on a product page, and the one each variant swaps in:
 * a percentage, the same figure the product cards show.
 */
class ProductPageDiscountBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function onSale(string $type, int $value, int $priceCents = 1690): Product
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5, 'price_cents' => $priceCents]);

        Discount::query()->create(['product_id' => $product->id, 'type' => $type, 'value' => $value]);

        return $product;
    }

    private function page(Product $product): string
    {
        return $this->get(localized_route('products.show', ['product' => $product->slug]))
            ->assertOk()
            ->getContent();
    }

    public function test_a_euro_discount_badge_reads_as_a_percentage(): void
    {
        // 2 € off 16,90 €.
        $html = $this->page($this->onSale('fixed', 200));

        $this->assertMatchesRegularExpression('/id="product-detail-discount-badge"[^>]*>\s*-11%<\/span>/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="product-detail-discount-badge"[^>]*>\s*-2,00/', $html);
    }

    public function test_a_percentage_badge_is_unchanged(): void
    {
        $html = $this->page($this->onSale('percentage', 20));

        $this->assertMatchesRegularExpression('/id="product-detail-discount-badge"[^>]*>\s*-20%<\/span>/', $html);
    }

    public function test_a_variant_sold_at_the_product_price_swaps_in_the_same_percentage(): void
    {
        $product = $this->onSale('fixed', 200);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Couleur', 'value' => 'Rouge']],
            'price_cents' => null,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $this->assertStringContainsString('data-variant-discount-label="-11%"', $this->page($product));
    }

    public function test_a_variant_with_its_own_price_carries_no_discount_label(): void
    {
        // A variant priced on its own is sold at that price, discount or none.
        $product = $this->onSale('fixed', 200);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Couleur', 'value' => 'Noir']],
            'price_cents' => 2490,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $this->assertStringContainsString('data-variant-discount-label=""', $this->page($product));
    }
}
