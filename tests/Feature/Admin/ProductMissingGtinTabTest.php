<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Missing GTIN" tab of the product list.
 *
 * A product sold in several sizes carries its GTIN on each of them, not on
 * itself. Listing it as incomplete while every size already has one made the
 * tab a to-do list that could never be emptied, same problem the Missing SKU
 * tab had.
 */
class ProductMissingGtinTabTest extends TestCase
{
    use RefreshDatabase;

    private function variant(Product $product, ?string $gtin, bool $active = true): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => $gtin ?? 'x', 'fr' => $gtin ?? 'x'],
            'gtin' => $gtin,
            'quantity' => 1,
            'is_active' => $active,
            'sort_order' => 1,
        ]);
    }

    private function tab()
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=no-gtin')
            ->assertOk();
    }

    public function test_a_plain_product_without_a_gtin_is_listed(): void
    {
        $product = Product::factory()->create(['gtin' => null]);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_a_product_whose_variants_all_have_one_is_not_listed(): void
    {
        $product = Product::factory()->create(['gtin' => null]);
        $this->variant($product, '3663596000001');
        $this->variant($product, '3663596000002');

        // The heart of it: nothing is left to fill in.
        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_a_product_with_one_variant_still_missing_is_listed(): void
    {
        $product = Product::factory()->create(['gtin' => null]);
        $this->variant($product, '3663596000001');
        $this->variant($product, null);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_an_empty_string_counts_as_missing(): void
    {
        $product = Product::factory()->create(['gtin' => null]);
        $this->variant($product, '3663596000001');
        $this->variant($product, '');

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_an_inactive_variant_without_one_still_counts(): void
    {
        $product = Product::factory()->create(['gtin' => null]);
        $this->variant($product, '3663596000001');
        $this->variant($product, null, active: false);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_a_product_with_its_own_gtin_is_never_listed(): void
    {
        $product = Product::factory()->create(['gtin' => '3663596000123']);

        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_a_disabled_product_is_not_listed(): void
    {
        $product = Product::factory()->create(['gtin' => null, 'is_active' => false]);

        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_the_tab_count_matches_the_rows(): void
    {
        $covered = Product::factory()->create(['gtin' => null]);
        $this->variant($covered, '3663596000001');

        $partial = Product::factory()->create(['gtin' => null]);
        $this->variant($partial, '3663596000002');
        $this->variant($partial, null);

        Product::factory()->create(['gtin' => null]);
        Product::factory()->create(['gtin' => '3663596000999']);

        $response = $this->tab();

        $this->assertSame(2, $response->viewData('noGtinCount'));
        $this->assertCount(2, $response->viewData('products'));
        $this->assertFalse($response->viewData('products')->contains('id', $covered->id));
    }
}
