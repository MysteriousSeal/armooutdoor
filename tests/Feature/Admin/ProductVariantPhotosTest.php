<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Up to three photos per variant: saved in slots, shown as a gallery. */
class ProductVariantPhotosTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach (ProductVariant::query()->get() as $variant) {
            $this->files = [...$this->files, ...$variant->photos()];
        }

        foreach (array_unique($this->files) as $file) {
            @unlink(public_path('images/'.$file));
            @unlink(public_path('images/'.dirname($file).'/thumbs/'.basename($file)));
        }

        parent::tearDown();
    }

    /** A real WebP on disk, as the shop stores them. */
    private function webp(string $name): string
    {
        $relative = 'products/test-variant-photo-'.getmypid().'-'.$name.'.webp';
        $path = public_path('images/'.$relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $canvas = imagecreatetruecolor(200, 200);
        imagewebp($canvas, $path, 80);
        imagedestroy($canvas);

        $this->files[] = $relative;

        return $relative;
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'slug' => 'patch-groupe-sanguin-'.Str::lower(Str::random(4)),
            'image' => $this->webp('cover'),
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Product $product, array $variant, array $files = []): array
    {
        return [
            'name' => 'Patch groupe sanguin',
            'description' => '<p>Un patch.</p>',
            'category_id' => $product->category_id ?? Category::factory()->create()->id,
            'price' => '9.90',
            'quantity' => 0,
            'variants' => [0 => $variant],
            'variant_images' => [0 => $files],
        ];
    }

    public function test_a_new_variant_takes_three_photos(): void
    {
        $product = $this->product();

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/products/'.$product->id, $this->payload($product, [
                'attributes_text' => 'Groupe: A+',
                'sku' => 'PATCH-AP',
                'quantity' => 2,
                'is_active' => '1',
            ], [
                0 => UploadedFile::fake()->image('front.jpg', 300, 300),
                1 => UploadedFile::fake()->image('back.jpg', 300, 300),
                2 => UploadedFile::fake()->image('worn.jpg', 300, 300),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $variant = $product->variants()->firstOrFail();

        $this->assertCount(3, $variant->photos());
        $this->assertNotNull($variant->image);
        $this->assertNotNull($variant->image_2);
        $this->assertNotNull($variant->image_3);

        foreach ($variant->photos() as $photo) {
            $this->assertFileExists(public_path('images/'.$photo));
        }
    }

    public function test_emptying_a_slot_moves_the_next_photos_up(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PATCH-AP',
            'quantity' => 1,
            'image' => $first = $this->webp('first'),
            'image_2' => $second = $this->webp('second'),
            'image_3' => $third = $this->webp('third'),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/products/'.$product->id, $this->payload($product, [
                'id' => $variant->id,
                'attributes_text' => 'Groupe: A+',
                'sku' => 'PATCH-AP',
                'quantity' => 1,
                'is_active' => '1',
                'remove_images' => [0 => '1'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $variant->refresh();

        // The main photo is always the first slot.
        $this->assertSame($second, $variant->image);
        $this->assertSame($third, $variant->image_2);
        $this->assertNull($variant->image_3);
        $this->assertFileDoesNotExist(public_path('images/'.$first));
    }

    public function test_a_replaced_photo_keeps_its_slot_and_its_old_file_goes(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PATCH-AP',
            'quantity' => 1,
            'image' => $first = $this->webp('first'),
            'image_2' => $second = $this->webp('second'),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/products/'.$product->id, $this->payload($product, [
                'id' => $variant->id,
                'attributes_text' => 'Groupe: A+',
                'sku' => 'PATCH-AP',
                'quantity' => 1,
                'is_active' => '1',
            ], [
                1 => UploadedFile::fake()->image('new-back.jpg', 300, 300),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $variant->refresh();

        $this->assertSame($first, $variant->image);
        $this->assertNotSame($second, $variant->image_2);
        $this->assertNotNull($variant->image_2);
        $this->assertFileDoesNotExist(public_path('images/'.$second));
    }

    public function test_a_deleted_variant_takes_its_photos_with_it(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PATCH-AP',
            'quantity' => 1,
            'image' => $first = $this->webp('first'),
            'image_3' => $third = $this->webp('third'),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/products/'.$product->id, $this->payload($product, [
                'id' => $variant->id,
                '_delete' => '1',
            ]))
            ->assertRedirect();

        $this->assertModelMissing($variant);
        $this->assertFileDoesNotExist(public_path('images/'.$first));
        $this->assertFileDoesNotExist(public_path('images/'.$third));
    }

    public function test_the_edit_page_shows_three_slots_per_variant(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PATCH-AP',
            'quantity' => 1,
            'image' => $this->webp('first'),
            'image_2' => $this->webp('second'),
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('Main photo')
            ->assertSee('name="variant_images[0][2]"', false)
            ->assertSee('name="variants[0][remove_images][1]"', false)
            ->assertSee('download="patch-ap_2.jpg"', false)
            ->assertDontSee('name="variants[0][remove_images][2]"', false)
            ->getContent();

        // The empty template for a new variant carries the three slots too.
        $this->assertStringContainsString('name="variant_images[__INDEX__][2]"', $html);
    }

    public function test_the_product_page_opens_on_the_selected_variant_photos(): void
    {
        $product = $this->product();
        ProductVariant::create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Groupe', 'value' => 'A+']],
            'sku' => 'PATCH-AP',
            'quantity' => 3,
            'is_active' => true,
            'image' => $first = $this->webp('first'),
            'image_2' => $second = $this->webp('second'),
        ]);

        $html = $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->getContent();

        // The gallery opens on the in-stock variant's photos...
        $this->assertStringContainsString('id="product-detail-main-image"', $html);
        $this->assertMatchesRegularExpression('#id="product-detail-main-image"\s+src="[^"]*'.preg_quote(basename($first), '#').'"#', $html);
        $this->assertStringContainsString('data-full-src="'.asset('images/'.$second).'"', $html);
        // ...each variant carries its own set for the switch...
        $this->assertStringContainsString('data-variant-images=', $html);
        // ...and the product's gallery stays at hand for a variant without photos.
        $this->assertStringContainsString('data-default-gallery=', $html);
    }
}
