<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The « One image only » tab: the main image is always set, so a product
 * with no gallery row shows exactly one photo on its page.
 */
class ProductOneImageTabTest extends TestCase
{
    use RefreshDatabase;

    private function product(bool $active, int $gallery): Product
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'is_active' => $active,
        ]);

        for ($i = 0; $i < $gallery; $i++) {
            ProductImage::query()->create(['product_id' => $product->id, 'image' => 'products/x'.$i.'.webp', 'sort_order' => $i]);
        }

        return $product;
    }

    public function test_it_lists_only_the_products_with_an_empty_gallery(): void
    {
        $alone = $this->product(true, 0);
        $withGallery = $this->product(true, 1);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=no-image')
            ->assertOk()
            ->assertSee($alone->name)
            ->assertDontSee($withGallery->name);
    }

    public function test_a_disabled_product_is_not_work_to_be_done(): void
    {
        $this->product(false, 0);
        $this->product(true, 0);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=no-image')->assertOk()->getContent();

        // The count beside the tab, and the rows, both leave it out.
        $this->assertMatchesRegularExpression('/One image only <span class="admin-tab-count">1</', $html);
    }

    public function test_the_tab_is_offered_on_every_products_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('tab=no-image', false)
            ->assertSee('One image only');
    }
}
