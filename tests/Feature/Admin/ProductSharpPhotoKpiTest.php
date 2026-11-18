<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header figure for photos of at least 1024 px on their shorter side:
 * every photo of the active products, the percentage taken over images.
 */
class ProductSharpPhotoKpiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file(public_path('images/'.$file))) {
                unlink(public_path('images/'.$file));
            }
        }

        parent::tearDown();
    }

    private function webp(string $name, int $width, int $height): string
    {
        $relative = 'products/test-cover-'.getmypid().'-'.$name.'.webp';
        $path = public_path('images/'.$relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagewebp($canvas, $path, 60);
        imagedestroy($canvas);
        $this->files[] = $relative;

        return $relative;
    }

    private function header(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();
    }

    public function test_every_photo_counts_and_the_percentage_is_over_images(): void
    {
        $product = Product::factory()->create(['image' => $this->webp('cover', 1254, 1254)]);
        ProductImage::create(['product_id' => $product->id, 'image' => $this->webp('exact', 1024, 1024), 'sort_order' => 0]);
        ProductImage::create(['product_id' => $product->id, 'image' => $this->webp('small', 1000, 1000), 'sort_order' => 1]);
        // The shorter side decides: wide but not tall enough.
        ProductImage::create(['product_id' => $product->id, 'image' => $this->webp('wide', 1600, 900), 'sort_order' => 2]);
        ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => 'V-ON', 'quantity' => 1, 'is_active' => true,
            'image' => $this->webp('variant', 1500, 1500), 'image_2' => $this->webp('variant-small', 800, 800),
        ]);
        // Left out: an inactive variant and a disabled product are not on the shop.
        ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => 'V-OFF', 'quantity' => 1, 'is_active' => false,
            'image' => $this->webp('variant-off', 1500, 1500),
        ]);
        Product::factory()->create(['image' => $this->webp('off', 1500, 1500), 'is_active' => false]);

        // 1254, 1024 and 1500 of six photos: half, not one product of one.
        $this->assertStringContainsString('3 of 6 images at 1024 px · 50.000%', $this->header());
    }

    public function test_a_missing_file_counts_but_not_as_sharp(): void
    {
        Product::factory()->create(['image' => 'products/gone.webp']);
        Product::factory()->create(['image' => $this->webp('ok', 1500, 1500)]);

        $this->assertStringContainsString('1 of 2 images at 1024 px · 50.000%', $this->header());
    }
}
