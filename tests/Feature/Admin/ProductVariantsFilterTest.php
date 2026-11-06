<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Variants filter of the product list: sold in variants or not. */
class ProductVariantsFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function withVariant(bool $active = true): Product
    {
        $product = Product::factory()->create();
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'VAR-'.$product->id,
            'quantity' => 1,
            'is_active' => $active,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
        ]);

        return $product;
    }

    public function test_the_filter_keeps_products_with_or_without_variants(): void
    {
        $plain = Product::factory()->create();
        $sized = $this->withVariant();
        // Inactive variants still make it a product sold in variants, as the
        // Variants column counts them.
        $dormant = $this->withVariant(active: false);

        $listed = fn (string $query) => $this->actingAs($this->admin())
            ->get('/admin/products'.$query)
            ->assertOk()
            ->viewData('products')
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$sized->id, $dormant->id], $listed('?variants=yes'));
        $this->assertSame([$plain->id], $listed('?variants=no'));
        $this->assertEqualsCanonicalizing([$plain->id, $sized->id, $dormant->id], $listed(''));
        // An unknown value filters nothing.
        $this->assertCount(3, $listed('?variants=maybe'));
    }

    public function test_an_active_filter_shows_its_chip_and_survives_the_tabs(): void
    {
        $this->withVariant();

        $this->actingAs($this->admin())->get('/admin/products?variants=yes')
            ->assertSee('Variants · With variants')
            ->assertSee('<option value="yes" selected>With variants</option>', false)
            ->assertSee('tab=disabled&amp;sort=', false)
            ->assertSee('variants=yes', false);
    }

    public function test_the_export_follows_the_filter(): void
    {
        $plain = Product::factory()->create(['sku' => 'PLAIN-ONLY']);
        $this->withVariant()->update(['sku' => 'SIZED-ONLY']);

        $csv = $this->actingAs($this->admin())->get('/admin/products/export?variants=no')->assertOk()->streamedContent();

        $this->assertStringContainsString('PLAIN-ONLY', $csv);
        $this->assertStringNotContainsString('SIZED-ONLY', $csv);
    }
}
