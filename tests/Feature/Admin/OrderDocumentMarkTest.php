<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mark on the two documents a customer keeps: the invoice and the
 * delivery slip.
 */
class OrderDocumentMarkTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            // The invoice is only offered once the order has left « placed ».
            'status' => 'shipped',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 1000, 'payment_method' => 'card',
        ]);
    }

    public function test_both_documents_draw_the_mark_beside_the_wordmark(): void
    {
        $order = $this->order();
        $admin = User::factory()->admin()->create();

        foreach (['/admin/orders/'.$order->number.'/invoice', '/admin/orders/'.$order->number.'/delivery-slip'] as $url) {
            $pdf = $this->actingAs($admin)->get($url);

            $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
            // A PDF that came out empty would still be a valid PDF, so the
            // size is the only honest signal that something was drawn.
            $this->assertGreaterThan(2000, strlen($pdf->getContent()));
        }
    }

    public function test_the_templates_read_the_brand_source_rather_than_a_copy(): void
    {
        foreach (['invoice-pdf', 'delivery-slip-pdf'] as $view) {
            $blade = file_get_contents(resource_path('views/admin/orders/'.$view.'.blade.php'));

            // The print variant, whose box is cropped to the drawing so the
            // renderer sizes it like any other image.
            $this->assertStringContainsString("resource_path('brand/armo-mark-print.svg')", $blade);
        }

        $this->assertFileExists(resource_path('brand/armo-mark-print.svg'));
    }
}
