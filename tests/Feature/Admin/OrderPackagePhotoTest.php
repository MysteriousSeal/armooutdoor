<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A photo of the packed parcel, kept on the order as proof. */
class OrderPackagePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function order(string $status = 'preparing'): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => [
                'first_name' => 'Julien', 'last_name' => 'Marchand', 'line1' => '4 rue des Lilas',
                'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR', 'phone' => '0612345678',
            ],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['slug' => 'colissimo', 'name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 990,
            'shipping_cents' => 350,
            'discount_cents' => 0,
            'total_cents' => 1340,
            'payment_method' => 'card',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_photo_can_be_uploaded_and_opened(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('colis.jpg', 800, 600)])
            ->assertSessionHasNoErrors();

        $order->refresh();
        Storage::disk('local')->assertExists($order->package_photo_path);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.package-photo.show', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    /** Whatever came in, WebP is what is kept, like the shop's other images. */
    public function test_the_photo_is_stored_as_webp(): void
    {
        foreach (['colis.jpg', 'colis.png', 'colis.webp'] as $name) {
            $order = $this->order();

            $this->actingAs($this->admin())
                ->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image($name, 400, 300)])
                ->assertSessionHasNoErrors();

            $path = $order->refresh()->package_photo_path;
            $this->assertStringEndsWith('.webp', $path, $name);
            $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('local')->get($path))['mime'], $name);
        }
    }

    /** A full-size phone photo is brought down to 2000 px on its long side. */
    public function test_a_large_photo_is_scaled_down_keeping_its_proportions(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('big.jpg', 3000, 1500)]);

        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($order->refresh()->package_photo_path));
        $this->assertSame([2000, 1000], [$width, $height]);
    }

    /**
     * A phone stores a portrait shot landscape with an EXIF flag saying how
     * it was held; GD ignores the flag, so the photo is turned before saving.
     */
    public function test_a_photo_is_turned_the_way_the_phone_was_held(): void
    {
        $order = $this->order();
        $landscape = UploadedFile::fake()->image('portrait.jpg', 300, 150);
        $jpeg = file_get_contents($landscape->getRealPath());
        // A minimal EXIF block: one IFD entry, Orientation (0x0112) = 6, "rotate 90° clockwise".
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00";
        $payload = "Exif\x00\x00".$tiff;
        file_put_contents($landscape->getRealPath(), substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2));

        $this->actingAs($this->admin())
            ->post(route('admin.orders.package-photo.store', $order), ['package_photo' => $landscape])
            ->assertSessionHasNoErrors();

        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($order->refresh()->package_photo_path));
        $this->assertSame([150, 300], [$width, $height]);
    }

    /** A small one is not blown up. */
    public function test_a_small_photo_keeps_its_size(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('small.jpg', 640, 480)]);

        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($order->refresh()->package_photo_path));
        $this->assertSame([640, 480], [$width, $height]);
    }

    public function test_png_and_webp_are_accepted(): void
    {
        foreach (['colis.png', 'colis.webp'] as $name) {
            $order = $this->order();

            $this->actingAs($this->admin())
                ->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image($name)])
                ->assertSessionHasNoErrors();

            $this->assertNotNull($order->refresh()->package_photo_path, $name);
        }
    }

    public function test_a_file_that_is_not_a_photo_is_refused(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.package-photo.store', $order), [
                'package_photo' => UploadedFile::fake()->create('colis.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('package_photo');

        $this->assertNull($order->refresh()->package_photo_path);
    }

    public function test_uploading_again_replaces_the_file(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('a.jpg')]);
        $first = $order->refresh()->package_photo_path;

        $this->actingAs($admin)->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('b.jpg')]);

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($order->refresh()->package_photo_path);
    }

    public function test_a_photo_can_be_removed(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('a.jpg')]);
        $path = $order->refresh()->package_photo_path;

        $this->actingAs($admin)->delete(route('admin.orders.package-photo.destroy', $order));

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($order->refresh()->package_photo_path);
    }

    /** It can show the address: admins only. */
    public function test_a_customer_cannot_reach_it(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('a.jpg')]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.package-photo.show', $order))
            ->assertRedirect();
    }

    public function test_a_draft_has_none(): void
    {
        $order = $this->order('draft');

        $this->actingAs($this->admin())
            ->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertNotFound();
    }

    /** Under the shipping label: a drop zone, then a thumbnail with Replace and Remove. */
    public function test_the_order_page_offers_the_photo_below_the_label(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->getContent();
        $this->assertStringContainsString('Upload a package photo', $html);
        $this->assertLessThan(strpos($html, 'Package photo</span>'), strpos($html, 'Shipping label</span>'));

        $this->actingAs($admin)->post(route('admin.orders.package-photo.store', $order), ['package_photo' => UploadedFile::fake()->image('a.jpg')]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('class="order-package-photo" src="'.route('admin.orders.package-photo.show', $order).'"', false)
            ->assertSee($order->number.'.webp')
            ->assertSee('remove-package-photo', false)
            ->assertDontSee('Upload a package photo');
    }
}
