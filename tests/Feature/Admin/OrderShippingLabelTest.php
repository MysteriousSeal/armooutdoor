<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The carrier's shipping label, kept on the order as a PDF. */
class OrderShippingLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function pdf(string $name = 'label.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'preparing',
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

    public function test_a_label_can_be_uploaded_and_opened(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.shipping-label.store', $order), ['shipping_label' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $order->refresh();
        Storage::disk('local')->assertExists($order->shipping_label_path);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.shipping-label.show', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_order_page_offers_the_label_once_uploaded(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Upload the shipping label')
            ->assertDontSee('Remove');

        $this->actingAs($admin)->post(route('admin.orders.shipping-label.store', $order), ['shipping_label' => $this->pdf()]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Replace')
            ->assertSee(route('admin.orders.shipping-label.show', $order), false)
            ->assertSee('Remove');
    }

    public function test_uploading_again_replaces_the_file(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.orders.shipping-label.store', $order), ['shipping_label' => $this->pdf()]);
        $first = $order->refresh()->shipping_label_path;

        $this->actingAs($admin)->post(route('admin.orders.shipping-label.store', $order), ['shipping_label' => $this->pdf()]);

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($order->refresh()->shipping_label_path);
    }

    public function test_only_a_pdf_is_accepted(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.shipping-label.store', $order), [
                'shipping_label' => UploadedFile::fake()->create('label.png', 10, 'image/png'),
            ])
            ->assertSessionHasErrors('shipping_label');

        $this->assertNull($order->refresh()->shipping_label_path);
    }

    public function test_a_label_can_be_removed(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.orders.shipping-label.store', $order), ['shipping_label' => $this->pdf()]);
        $path = $order->refresh()->shipping_label_path;

        $this->actingAs($admin)->delete(route('admin.orders.shipping-label.destroy', $order));

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($order->refresh()->shipping_label_path);
    }

    public function test_a_customer_cannot_reach_it(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.shipping-label.store', $order), ['shipping_label' => $this->pdf()])
            ->assertRedirect();

        $this->assertNull($order->refresh()->shipping_label_path);
    }
}
