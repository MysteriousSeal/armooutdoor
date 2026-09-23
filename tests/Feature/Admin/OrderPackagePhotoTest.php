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
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private');
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
            ->assertSee($order->number.'.jpg')
            ->assertSee('remove-package-photo', false)
            ->assertDontSee('Upload a package photo');
    }
}
