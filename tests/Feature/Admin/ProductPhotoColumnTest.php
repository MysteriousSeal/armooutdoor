<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Photos column of the product list: main photo plus gallery, against
 * the five every product should have.
 */
class ProductPhotoColumnTest extends TestCase
{
    use RefreshDatabase;

    private function list(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();
    }

    private function productWithPhotos(int $gallery, bool $main = true): Product
    {
        $product = Product::factory()->create(['image' => $main ? 'products/main.webp' : '']);

        for ($i = 1; $i <= $gallery; $i++) {
            ProductImage::query()->create(['product_id' => $product->id, 'image' => "products/{$i}.webp", 'sort_order' => $i]);
        }

        return $product;
    }

    public function test_no_photo_is_red(): void
    {
        $this->productWithPhotos(0, main: false);

        $this->assertStringContainsString('is-out-of-stock" title="No photo on our shop, five wanted">0</span>', $this->list());
    }

    public function test_a_single_photo_is_red(): void
    {
        $this->productWithPhotos(0);

        $this->assertStringContainsString('is-out-of-stock" title="Only one photo on our shop, five wanted">1</span>', $this->list());
    }

    public function test_the_main_photo_and_the_gallery_are_counted_together_and_short_of_five_is_amber(): void
    {
        $this->productWithPhotos(3);

        $this->assertStringContainsString('is-low-stock" title="4 photos on our shop, five wanted">4</span>', $this->list());
    }

    public function test_five_photos_are_green(): void
    {
        $this->productWithPhotos(4);

        $this->assertStringContainsString('is-in-stock" title="5 photos on our shop">5</span>', $this->list());
    }

    public function test_the_less_than_five_tab_holds_two_to_four_photos(): void
    {
        $one = $this->productWithPhotos(0);
        $two = $this->productWithPhotos(1);
        $four = $this->productWithPhotos(3);
        $five = $this->productWithPhotos(4);
        $disabled = $this->productWithPhotos(2);
        $disabled->update(['is_active' => false]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=few-images')
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$two->id, $four->id],
            $response->viewData('products')->pluck('id')->all(),
        );
        $this->assertSame(2, $response->viewData('fewImagesCount'));
        $response->assertSee('Less than 5 images');
    }
}
