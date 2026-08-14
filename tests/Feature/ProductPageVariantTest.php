<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_product_without_variants_still_renders_the_plain_add_to_cart_form(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->get('/products/ridge-tent')
            ->assertOk()
            ->assertSee('name="product_id"', false)
            ->assertDontSee('name="variant_id"', false);
    }

    public function test_a_product_with_variants_renders_selectable_variant_options(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille M']],
            'price_cents' => 39900,
            'quantity' => 5,
            'is_active' => true,
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille L']],
            'price_cents' => 42900,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $response = $this->get('/products/ridge-tent')->assertOk();

        $response->assertSee('name="variant_id"', false);
        $response->assertSee('Taille M');
        $response->assertSee('Taille L');
        $response->assertSee('399,00');
        $response->assertSee('429,00');
    }

    public function test_a_variant_only_out_of_stock_product_hides_the_add_to_cart_form(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $product->update(['quantity' => 0]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille M']],
            'quantity' => 4,
            'is_active' => true,
        ]);

        $this->get('/products/ridge-tent')
            ->assertOk()
            ->assertSee('name="variant_id"', false);
    }
}
