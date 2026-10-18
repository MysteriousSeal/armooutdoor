<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class AdminOrderPickingSlipTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status, array $overrides = []): Order
    {
        $address = ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'];

        $order = Order::query()->create($overrides + [
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => $address,
            'billing_address_snapshot' => $address,
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
        ]);

        $order->items()->create(['name' => ['fr' => 'Gourde'], 'product_slug' => 'gourde', 'image' => '', 'sku' => 'GOU-1', 'unit_price_cents' => 1000, 'quantity' => 2, 'line_cents' => 2000]);

        return $order;
    }

    public function test_it_adds_up_only_placed_and_preparing_live_orders(): void
    {
        $this->order('placed');
        $this->order('preparing');
        $this->order('shipped');
        $this->order('draft');
        $this->order('placed', ['archived_at' => now()]);
        $this->order('placed', ['test_marked_at' => now()]);

        $captured = null;
        View::composer('admin.orders.picking-slip-pdf', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.picking-slip'))
            ->assertOk();

        $this->assertSame(2, $captured['orderCount']);
        $this->assertCount(1, $captured['lines']);
        $this->assertSame(4, $captured['lines']->first()['quantity']);
    }

    public function test_it_downloads_a_pdf(): void
    {
        $this->order('placed');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.picking-slip'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_it_is_closed_to_guests(): void
    {
        $this->get(route('admin.orders.picking-slip'))->assertRedirect();
    }
}
