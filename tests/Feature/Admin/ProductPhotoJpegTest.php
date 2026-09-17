<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Each photo of the edit page, handed over as a JPEG. */
class ProductPhotoJpegTest extends TestCase
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

    /** A real WebP on disk, as the shop stores them. */
    private function webp(string $name, int $width = 800, int $height = 600): string
    {
        $relative = 'products/test-photo-'.getmypid().'-'.$name.'.webp';
        $path = public_path('images/'.$relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 40, 90, 140));
        imagewebp($canvas, $path, 82);
        imagedestroy($canvas);

        $this->files[] = $relative;

        return $relative;
    }

    /** @return array{0: Product, 1: ProductImage, 2: ProductImage} */
    private function productWithGallery(): array
    {
        $product = Product::factory()->create([
            'sku' => 'CRT-REACT-076',
            'slug' => 'cible-ronde',
            'image' => $this->webp('cover'),
        ]);

        $first = ProductImage::create(['product_id' => $product->id, 'image' => $this->webp('first'), 'sort_order' => 0]);
        $second = ProductImage::create(['product_id' => $product->id, 'image' => $this->webp('second'), 'sort_order' => 1]);

        return [$product, $first, $second];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_cover_is_photo_one_named_after_the_sku(): void
    {
        [$product] = $this->productWithGallery();

        $body = $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/photos/cover.jpg')
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertDownload('crt-react-076_1.jpg')
            ->getContent();

        $this->assertStringStartsWith("\xFF\xD8\xFF", $body);
        $this->assertSame([800, 600], array_slice(getimagesizefromstring($body), 0, 2));
    }

    public function test_gallery_images_follow_the_cover_in_the_saved_order(): void
    {
        [$product, $first, $second] = $this->productWithGallery();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/products/'.$product->id.'/photos/'.$first->id.'.jpg')
            ->assertOk()
            ->assertDownload('crt-react-076_2.jpg');

        $this->actingAs($admin)
            ->get('/admin/products/'.$product->id.'/photos/'.$second->id.'.jpg')
            ->assertOk()
            ->assertDownload('crt-react-076_3.jpg');
    }

    public function test_the_slug_stands_in_for_a_missing_sku(): void
    {
        [$product, $first] = $this->productWithGallery();
        $product->update(['sku' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/photos/'.$first->id.'.jpg')
            ->assertOk()
            ->assertDownload('cible-ronde_2.jpg');
    }

    public function test_the_stored_webp_is_left_alone(): void
    {
        [$product, $first] = $this->productWithGallery();
        $before = file_get_contents(public_path('images/'.$first->image));

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/photos/'.$first->id.'.jpg')
            ->assertOk();

        $this->assertSame($before, file_get_contents(public_path('images/'.$first->image)));
    }

    public function test_an_image_of_another_product_is_not_served(): void
    {
        [$product] = $this->productWithGallery();
        $other = Product::factory()->create();
        $foreign = ProductImage::create(['product_id' => $other->id, 'image' => $this->webp('foreign'), 'sort_order' => 0]);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/photos/'.$foreign->id.'.jpg')
            ->assertNotFound();
    }

    public function test_a_variant_photo_is_named_after_the_variant_sku(): void
    {
        [$product] = $this->productWithGallery();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CRT-REACT-076-JAUNE',
            'quantity' => 1,
            'image' => $this->webp('variant'),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/variants/'.$variant->id.'/photo.jpg')
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertDownload('crt-react-076-jaune.jpg');
    }

    public function test_a_variant_without_its_own_photo_has_nothing_to_hand_over(): void
    {
        [$product] = $this->productWithGallery();
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'NO-PHOTO', 'quantity' => 1]);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/variants/'.$variant->id.'/photo.jpg')
            ->assertNotFound();
    }

    public function test_the_edit_page_links_each_saved_photo(): void
    {
        [$product, $first, $second] = $this->productWithGallery();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CRT-REACT-076-JAUNE',
            'quantity' => 1,
            'image' => $this->webp('variant'),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('/photos/cover.jpg', false)
            ->assertSee('/photos/'.$first->id.'.jpg', false)
            ->assertSee('/photos/'.$second->id.'.jpg', false)
            ->assertSee('/variants/'.$variant->id.'/photo.jpg', false)
            ->assertSee('download="crt-react-076_3.jpg"', false)
            ->assertSee('download="crt-react-076-jaune.jpg"', false)
            // The header button stays.
            ->assertSee('Cover as JPG');
    }

    public function test_only_an_admin_can_take_them(): void
    {
        [$product, $first] = $this->productWithGallery();
        $url = '/admin/products/'.$product->id.'/photos/'.$first->id.'.jpg';

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->get($url)->assertRedirect();
    }
}
