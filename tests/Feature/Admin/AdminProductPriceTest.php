<?php

namespace Tests\Feature\Admin;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * What the product list says a product costs.
 *
 * It printed the reduced figure alone, so a discounted product showed a price
 * that was not the catalogue price with nothing saying why.
 */
class AdminProductPriceTest extends TestCase
{
    use RefreshDatabase;

    private function list(): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'owner', 'is_admin' => true]))
            ->get('/admin/products')
            ->assertOk();
    }

    public function test_an_ordinary_product_shows_one_price(): void
    {
        Product::factory()->create(['is_active' => true, 'price_cents' => 3990]);

        $this->list()
            ->assertSee('39,90', false)
            ->assertDontSee('admin-price-cut', false);
    }

    public function test_a_reduced_product_shows_the_catalogue_price_first(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price_cents' => 3990]);
        Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 29]);

        $html = $this->list()->getContent();

        // Both figures, catalogue first.
        $this->assertStringContainsString('admin-price-cut', $html);
        $this->assertLessThan(
            strpos($html, 'admin-price-cut'),
            strpos($html, '39,90'),
            'The catalogue price should come before the reduction.',
        );
    }

    public function test_the_reduction_says_how_deep_it_goes(): void
    {
        // The amount alone leaves the reader subtracting one from the other.
        $product = Product::factory()->create(['is_active' => true, 'price_cents' => 3990]);
        Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 29]);

        $this->list()->assertSee('-29%', false);
    }

    public function test_a_discount_outside_its_window_is_not_shown(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price_cents' => 3990]);
        Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 29,
            'ends_at' => now()->subDay(),
        ]);

        $this->list()->assertDontSee('admin-price-cut', false);
    }

    public function test_a_variant_with_its_own_price_is_not_reduced(): void
    {
        // The reduction belongs to the product's price; a variant that
        // overrides that price has stepped outside it.
        $product = Product::factory()->create(['is_active' => true, 'price_cents' => 3990]);
        Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 29]);

        $own = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'S']],
            'price_cents' => 4990,
            'quantity' => 3,
            'is_active' => true,
        ]);

        $this->assertFalse($own->isDiscounted());
        $this->assertSame('49,90 €', str_replace("\u{a0}", ' ', $own->formattedOriginalPrice()));
    }

    public function test_a_variant_without_its_own_price_follows_the_product(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price_cents' => 3990]);
        Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 29]);

        $inherits = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'price_cents' => null,
            'quantity' => 3,
            'is_active' => true,
        ]);

        $this->assertTrue($inherits->fresh()->isDiscounted());
    }

    public function test_the_list_does_not_ask_for_a_discount_row_by_row(): void
    {
        foreach (range(1, 12) as $i) {
            $product = Product::factory()->create(['is_active' => true, 'price_cents' => 1000 + $i]);
            Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 10]);
        }

        $admin = User::factory()->create(['role' => 'owner', 'is_admin' => true]);

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/products')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(35, $queries, "Expected a fixed number of queries, ran {$queries}");
    }
}
