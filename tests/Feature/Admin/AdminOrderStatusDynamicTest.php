<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A status change rewrites several regions of the order page. The server
 * re-renders it and the client swaps those regions, so the markup the client
 * needs must actually be in the response — and the plain form post has to
 * keep working for anyone without JavaScript.
 */
class AdminOrderStatusDynamicTest extends TestCase
{
    use RefreshDatabase;

    /** The ids the client swaps. */
    private const REGIONS = ['order-heading', 'order-actions', 'order-downloads', 'order-timeline', 'order-modals'];

    private function order(string $status = 'placed'): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
        ]);
    }

    public function test_the_page_marks_every_region_the_client_swaps(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $content = $this->actingAs($admin)->get('/admin/orders/'.$order->number)->assertOk()->getContent();

        foreach (self::REGIONS as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $content, "Missing region: {$id}");
        }
    }

    public function test_preparing_returns_the_rerendered_page(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.orders.prepare', $order))
            ->assertOk()
            ->assertJsonStructure(['message', 'status', 'html'])
            ->assertJsonPath('status', 'preparing');

        $html = $response->json('html');

        foreach (self::REGIONS as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "Response is missing region: {$id}");
        }
    }

    public function test_the_returned_html_reflects_the_new_status_not_the_old(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $html = $this->actingAs($admin)
            ->patchJson(route('admin.orders.prepare', $order))
            ->json('html');

        // The badge and the next action both move on.
        $this->assertStringContainsString('badge-preparing', $html);
        $this->assertStringContainsString('Mark as shipped', $html);
        $this->assertStringNotContainsString('Mark as being prepared', $html);
    }

    public function test_the_returned_html_carries_the_new_timeline_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $before = substr_count(
            $this->actingAs($admin)->get('/admin/orders/'.$order->number)->getContent(),
            'order-timeline-item',
        );

        $html = $this->actingAs($admin)->patchJson(route('admin.orders.ship', $order))->json('html');

        // Stale here would look like a bug: the status moved but its history
        // would not show it.
        $this->assertSame($before + 1, substr_count($html, 'order-timeline-item'));
        $this->assertStringContainsString('is-shipped', $html);
    }

    /**
     * Deux règles distinctes se croisent ici : le client attend l'expédition
     * (invoiceIsAvailable), l'admin considère la commande facturable dès
     * qu'elle est confirmée en préparation (adminInvoiceIsAvailable).
     */
    public function test_a_placed_order_has_no_invoice_for_anyone(): void
    {
        $order = $this->order('placed');

        $this->assertFalse($order->invoiceIsAvailable());
        $this->assertFalse($order->adminInvoiceIsAvailable());
    }

    public function test_a_preparing_order_is_invoiceable_for_the_admin_only(): void
    {
        $order = $this->order('preparing');

        $this->assertFalse($order->invoiceIsAvailable());
        $this->assertTrue($order->adminInvoiceIsAvailable());
    }

    public function test_preparing_makes_the_admin_invoice_download_appear(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('placed');

        $this->assertFalse($order->adminInvoiceIsAvailable());

        $html = $this->actingAs($admin)->patchJson(route('admin.orders.prepare', $order))->json('html');

        $this->assertStringContainsString(route('admin.orders.invoice', $order), $html);
    }

    public function test_shipping_still_makes_the_customer_invoice_download_appear(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('preparing');

        $this->assertFalse($order->invoiceIsAvailable());

        $html = $this->actingAs($admin)->patchJson(route('admin.orders.ship', $order))->json('html');

        $this->assertStringContainsString(route('admin.orders.invoice', $order), $html);
    }

    public function test_a_customer_cannot_download_an_invoice_while_preparing(): void
    {
        $order = $this->order('preparing');
        $customer = User::factory()->create();
        $order->update(['user_id' => $customer->id]);

        $this->actingAs($customer)
            ->get(route('orders.invoice', $order))
            ->assertNotFound();
    }

    public function test_an_admin_can_download_an_invoice_while_preparing(): void
    {
        $order = $this->order('preparing');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.invoice', $order))
            ->assertOk();
    }

    public function test_refunding_returns_the_rerendered_page(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('shipped');

        $this->actingAs($admin)
            ->patchJson(route('admin.orders.refund', $order))
            ->assertOk()
            ->assertJsonPath('status', 'refunded');

        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_a_plain_form_post_still_redirects(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        // No JavaScript: the ordinary flow must be untouched.
        $this->actingAs($admin)
            ->from('/admin/orders/'.$order->number)
            ->patch(route('admin.orders.prepare', $order))
            ->assertRedirect('/admin/orders/'.$order->number)
            ->assertSessionHas('status', 'Order marked as being prepared.');

        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_staff_are_still_refused_a_refund_over_json(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $order = $this->order('shipped');

        // The client falls back to a normal submit on a non-ok response, so
        // the admin still sees the real refusal.
        $this->actingAs($staff)
            ->patchJson(route('admin.orders.refund', $order))
            ->assertForbidden();

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_a_draft_is_still_refused_over_json(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('draft');

        $this->actingAs($admin)
            ->patchJson(route('admin.orders.prepare', $order))
            ->assertNotFound();

        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_the_status_change_is_still_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $this->actingAs($admin)->patchJson(route('admin.orders.prepare', $order));

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'order.preparing',
            'user_id' => $admin->id,
            'subject_id' => $order->id,
        ]);
    }

    public function test_the_page_loads_the_dynamic_script_and_the_toast(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$this->order()->number)
            ->assertOk()
            ->assertSee('js/admin-order-status.js', false)
            // Loaded from the layout, ahead of the scripts that call it.
            ->assertSee('js/admin-toast.js', false);
    }

    public function test_the_rerendered_html_carries_no_flash_banner(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->patchJson(route('admin.orders.prepare', $this->order()))
            ->json('html');

        // Confirmation is a toast now. A banner riding along in the swapped
        // markup would be a second, stale copy of the same message.
        $this->assertStringNotContainsString('flash-success', $html);
    }
}
