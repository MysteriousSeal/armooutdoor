<?php

namespace Tests\Feature\Admin;

use App\Models\Carrier;
use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(string $status = 'placed'): Order
    {
        $customer = User::factory()->create();

        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 5000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 5500,
            'payment_method' => 'card',
        ]);
    }

    public function test_admin_can_move_an_order_through_the_status_workflow(): void
    {
        $order = $this->order('placed');
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/prepare')->assertRedirect();
        $this->assertSame('preparing', $order->fresh()->status);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/ship')->assertRedirect();
        $this->assertSame('shipped', $order->fresh()->status);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/in-transit')->assertRedirect();
        $this->assertSame('in_transit', $order->fresh()->status);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/deliver')->assertRedirect();
        $this->assertSame('delivered', $order->fresh()->status);

        $this->assertSame(
            ['placed', 'preparing', 'shipped', 'in_transit', 'delivered'],
            $order->fresh()->statusHistories()->orderBy('id')->pluck('status')->all()
        );
    }

    public function test_admin_can_refund_a_shipped_order(): void
    {
        $order = $this->order('shipped');

        $this->actingAs($this->admin())
            ->patch('/admin/orders/'.$order->number.'/refund')
            ->assertRedirect();

        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_an_order_cannot_be_refunded_twice(): void
    {
        $order = $this->order('refunded');

        $this->actingAs($this->admin())
            ->patch('/admin/orders/'.$order->number.'/refund')
            ->assertForbidden();
    }

    public function test_a_draft_order_cannot_be_prepared_or_shipped(): void
    {
        $order = $this->order('draft');
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/prepare')->assertNotFound();
        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/ship')->assertNotFound();
    }

    public function test_admin_can_archive_and_unarchive_an_order(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/archive')->assertRedirect();
        $this->assertNotNull($order->fresh()->archived_at);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/unarchive')->assertRedirect();
        $this->assertNull($order->fresh()->archived_at);
    }

    public function test_admin_can_set_tracking_details(): void
    {
        $order = $this->order('preparing');
        $carrier = Carrier::query()->first();

        $this->actingAs($this->admin())
            ->patch('/admin/orders/'.$order->number.'/tracking', [
                'tracking_number' => 'TRACK123456',
                'tracking_carrier_id' => $carrier->id,
            ])
            ->assertRedirect();

        $this->assertSame('TRACK123456', $order->fresh()->tracking_number);
        $this->assertSame($carrier->id, $order->fresh()->tracking_carrier_id);
    }

    public function test_admin_can_record_shipping_paid_and_commission_for_a_marketplace_order(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'Vinted']);
        $order = $this->order();
        $order->update(['is_manual' => true, 'marketplace_id' => $marketplace->id]);

        $this->actingAs($this->admin())
            ->patch('/admin/orders/'.$order->number.'/shipping-paid', ['shipping_paid' => '4.50'])
            ->assertRedirect();
        $this->assertSame(450, $order->fresh()->shipping_paid_cents);

        $this->actingAs($this->admin())
            ->patch('/admin/orders/'.$order->number.'/marketplace-commission', ['marketplace_commission' => '2.10'])
            ->assertRedirect();
        $this->assertSame(210, $order->fresh()->marketplace_commission_cents);
    }

    public function test_shipping_paid_is_rejected_for_a_non_marketplace_order(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->patch('/admin/orders/'.$order->number.'/shipping-paid', ['shipping_paid' => '4.50'])
            ->assertNotFound();
    }

    public function test_admin_orders_index_can_be_filtered_by_status(): void
    {
        $placed = $this->order('placed');
        $shipped = $this->order('shipped');

        $response = $this->actingAs($this->admin())->get('/admin/orders?status=shipped');

        $response->assertOk()
            ->assertSee($shipped->number)
            ->assertDontSee($placed->number);
    }

    public function test_admin_orders_index_can_be_searched_by_order_number(): void
    {
        $order = $this->order();
        $other = $this->order();

        $this->actingAs($this->admin())
            ->get('/admin/orders?search='.$order->number)
            ->assertOk()
            ->assertSee($order->number)
            ->assertDontSee($other->number);
    }

    public function test_archived_orders_are_hidden_from_the_default_tab(): void
    {
        $order = $this->order();
        $order->archive();

        $this->actingAs($this->admin())
            ->get('/admin/orders')
            ->assertOk()
            ->assertDontSee($order->number);

        $this->actingAs($this->admin())
            ->get('/admin/orders?tab=archived')
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_admin_orders_csv_export_contains_order_numbers(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->admin())->get('/admin/orders/export');

        $response->assertOk();
        $this->assertStringContainsString($order->number, $response->streamedContent());
    }
}
