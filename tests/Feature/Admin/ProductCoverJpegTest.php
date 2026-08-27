<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The cover image, handed over as a JPEG. */
class ProductCoverJpegTest extends TestCase
{
    use RefreshDatabase;

    private string $image = '';

    protected function tearDown(): void
    {
        if ($this->image !== '' && is_file(public_path('images/'.$this->image))) {
            unlink(public_path('images/'.$this->image));
        }

        parent::tearDown();
    }

    /** A product whose cover is a real WebP on disk, as the shop stores them. */
    private function product(array $overrides = []): Product
    {
        $this->image = 'products/test-cover-'.getmypid().'.webp';
        $path = public_path('images/'.$this->image);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $canvas = imagecreatetruecolor(1000, 1000);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 40, 90, 140));
        imagewebp($canvas, $path, 82);
        imagedestroy($canvas);

        return Product::factory()->create(array_merge([
            'slug' => 'cible-ronde-10cm-fluo',
            'image' => $this->image,
        ], $overrides));
    }

    public function test_the_cover_comes_back_as_a_jpeg_named_after_the_product(): void
    {
        $product = $this->product();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/cover.jpg')
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertDownload('cible-ronde-10cm-fluo.jpg');

        // Really a JPEG, not a WebP wearing the name of one.
        $body = $response->getContent();
        $this->assertStringStartsWith("\xFF\xD8\xFF", $body);
    }

    public function test_the_full_size_is_kept(): void
    {
        $product = $this->product();

        $body = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/cover.jpg')
            ->assertOk()
            ->getContent();

        [$width, $height] = getimagesizefromstring($body);

        // The point is a full-size copy: a thumbnail would be no use to a
        // marketplace form.
        $this->assertSame(1000, $width);
        $this->assertSame(1000, $height);
    }

    public function test_the_stored_webp_is_left_alone(): void
    {
        $product = $this->product();
        $before = file_get_contents(public_path('images/'.$this->image));

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/cover.jpg')
            ->assertOk();

        // The conversion is a copy made for the download, nothing more.
        $this->assertSame($before, file_get_contents(public_path('images/'.$this->image)));
        $this->assertSame($this->image, $product->refresh()->image);
    }

    public function test_a_product_without_a_cover_has_nothing_to_hand_over(): void
    {
        $product = Product::factory()->create(['image' => '']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/cover.jpg')
            ->assertNotFound();
    }

    public function test_a_cover_whose_file_has_gone_answers_404(): void
    {
        $product = Product::factory()->create(['image' => 'products/parti-en-fumee.webp']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/cover.jpg')
            ->assertNotFound();
    }

    public function test_the_button_shows_only_once_an_image_is_saved(): void
    {
        $product = $this->product();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('Cover as JPG')
            ->assertSee('/cover.jpg', false);

        $bare = Product::factory()->create(['image' => '']);

        $this->actingAs($admin)
            ->get('/admin/products/'.$bare->id.'/edit')
            ->assertOk()
            ->assertDontSee('Cover as JPG');
    }

    public function test_only_an_admin_can_take_it(): void
    {
        $product = $this->product();
        $url = '/admin/products/'.$product->id.'/cover.jpg';

        auth()->logout();
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->get($url)->assertRedirect();
    }
}
