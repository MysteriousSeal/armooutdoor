<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Orders placed while testing are kept, not deleted — the record is worth
 * having. Marking one takes it out of every figure in the admin without
 * touching what the customer sees, and without pretending to undo anything
 * the order actually did.
 */
class TestOrderTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $attributes['user_id'] ?? User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            ...$attributes,
        ]);
    }

    public function test_marking_and_unmarking_move_the_timestamp(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $this->actingAs($admin)->patch(route('admin.orders.test', $order))->assertRedirect();
        $this->assertNotNull($order->fresh()->test_marked_at);
        $this->assertTrue($order->fresh()->isTest());

        $this->actingAs($admin)->patch(route('admin.orders.untest', $order))->assertRedirect();
        $this->assertNull($order->fresh()->test_marked_at);
    }

    public function test_a_signed_out_visitor_cannot_mark_an_order(): void
    {
        $order = $this->order();

        $this->patch(route('admin.orders.test', $order))->assertRedirect();
        $this->assertNull($order->fresh()->test_marked_at);
    }

    public function test_staff_cannot_mark_an_order_as_test(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $marked = $this->order(['test_marked_at' => now()]);
        $order = $this->order();

        // Archiving only hides an order; this moves revenue, so it sits with
        // refunding rather than with archiving.
        $this->actingAs($staff)->patch(route('admin.orders.test', $order))->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.orders.untest', $marked))->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.orders.bulk-test'), ['order_ids' => [$order->id]])->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.orders.bulk-untest'), ['order_ids' => [$marked->id]])->assertForbidden();

        $this->assertNull($order->fresh()->test_marked_at);
        $this->assertNotNull($marked->fresh()->test_marked_at);
    }

    public function test_staff_are_not_offered_the_control_they_cannot_use(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $order = $this->order();

        $this->actingAs($staff)->get('/admin/orders')->assertOk()->assertDontSee('Mark as test');
        $this->actingAs($staff)->get('/admin/orders/'.$order->number)->assertOk()->assertDontSee('Mark as test');
    }

    public function test_a_test_order_is_still_findable_in_search_and_marked_there(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order(['test_marked_at' => now()]);

        // Search is how you reach one to unmark it, so it stays findable —
        // but never without saying what it is.
        $this->actingAs($admin)->get('/admin/search?q='.$order->number)->assertOk()
            ->assertSee($order->number)
            ->assertSee('order-chip--test', false);
    }

    public function test_bulk_marking_skips_orders_already_marked(): void
    {
        $admin = User::factory()->admin()->create();
        $fresh = $this->order();
        $already = $this->order(['test_marked_at' => Carbon::parse('2026-08-01 10:00')]);

        // Someone else may have marked one while this page sat open, and that
        // is no reason to fail the batch or overstate what changed.
        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-test'), ['order_ids' => [$fresh->id, $already->id]])
            ->assertRedirect()
            ->assertSessionHas('status', '1 order marked as test.');

        $this->assertNotNull($fresh->fresh()->test_marked_at);
        $this->assertSame(
            '2026-08-01 10:00:00',
            $already->fresh()->test_marked_at->format('Y-m-d H:i:s'),
            'An already-marked order should keep its original timestamp.',
        );
    }

    public function test_bulk_unmarking_reports_nothing_when_none_were_marked(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-untest'), ['order_ids' => [$order->id]])
            ->assertRedirect()
            ->assertSessionHas('status', 'Nothing to unmark as test.');
    }

    public function test_both_actions_are_written_to_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $this->actingAs($admin)->patch(route('admin.orders.test', $order));
        $this->actingAs($admin)->patch(route('admin.orders.untest', $order));

        $actions = AdminActivityLog::query()->pluck('action')->all();
        $this->assertContains('order.marked_test', $actions);
        $this->assertContains('order.unmarked_test', $actions);
    }

    public function test_a_test_order_leaves_the_orders_tab_for_the_test_tab(): void
    {
        $admin = User::factory()->admin()->create();
        $test = $this->order(['test_marked_at' => now()]);
        $real = $this->order();

        $this->actingAs($admin)->get('/admin/orders')->assertOk()
            ->assertSee($real->number)
            ->assertDontSee($test->number);

        $this->actingAs($admin)->get('/admin/orders?tab=test')->assertOk()
            ->assertSee($test->number)
            ->assertDontSee($real->number);
    }

    public function test_an_archived_test_order_is_in_the_test_tab_only(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order(['test_marked_at' => now(), 'archived_at' => now()]);

        // Otherwise the same order sits in two tabs, and the two tab counts
        // added together overstate how many orders exist.
        $this->actingAs($admin)->get('/admin/orders?tab=archived')->assertOk()->assertDontSee($order->number);
        $this->actingAs($admin)->get('/admin/orders?tab=test')->assertOk()->assertSee($order->number);
    }

    public function test_the_customer_sees_no_difference(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = $this->order(['user_id' => $customer->id]);

        $before = $this->actingAs($customer)->get('/orders/'.$order->number)->assertOk()->getContent();

        $this->actingAs($admin)->patch(route('admin.orders.test', $order));

        // The admin's flash rides the shared test session into the next
        // request; a real admin and customer never share one. Burn it off so
        // the comparison is about the order, not the session.
        $this->actingAs($customer)->get('/orders/'.$order->number);

        $after = $this->actingAs($customer)->get('/orders/'.$order->number)->assertOk()->getContent();

        // An admin's bookkeeping is not the customer's business.
        $this->assertSame($before, $after);
    }

    public function test_the_customers_profile_lists_the_order_but_leaves_it_out_of_the_total(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->order(['user_id' => $customer->id]);
        $test = $this->order(['user_id' => $customer->id, 'test_marked_at' => now()]);

        // The profile stays a full record of what this customer did, so the
        // order is listed — but the page has to say why the total disagrees.
        $this->actingAs($admin)->get('/admin/customers/'.$customer->id)->assertOk()
            ->assertSee($test->number)
            ->assertSee('badge-test', false)
            ->assertSee('excluding 1 test order')
            // The total lives in the "Total spent" tile now, amount on its
            // own line rather than a "… spent" chip.
            ->assertSee('Total spent')
            ->assertSee(format_euros(1500));
    }

    public function test_the_customer_list_agrees_with_the_customer_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->order(['user_id' => $customer->id, 'test_marked_at' => now()]);

        $list = $this->actingAs($admin)->get('/admin/customers')->assertOk();
        $row = $list->viewData('customers')->firstWhere('id', $customer->id);
        $profile = $this->actingAs($admin)->get('/admin/customers/'.$customer->id)->assertOk();

        // Two pages describing the same customer must not disagree: the list
        // once credited them with an order their own profile denied.
        $this->assertSame(0, $row->orders_count);
        $this->assertSame(0, (int) $row->spent_cents);
        $this->assertSame(0, $profile->viewData('spentCents'));
        $this->assertNull($row->last_order_at);

        // And the tabs follow the same rule.
        $this->assertSame(0, $list->viewData('withOrdersCount'));
        $this->assertFalse(
            $this->actingAs($admin)->get('/admin/customers?tab=with-orders')
                ->viewData('customers')->contains('id', $customer->id),
        );
        $this->assertTrue(
            $this->actingAs($admin)->get('/admin/customers?tab=no-orders')
                ->viewData('customers')->contains('id', $customer->id),
        );
    }

    /**
     * The one that matters. Every figure is asserted together rather than in
     * its own test, so a call site that never got excludingTest() fails here
     * instead of hiding behind the ones that did.
     */
    public function test_a_test_order_is_absent_from_every_figure_at_once(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        // The fixture has to give every figure something to get wrong. A
        // manual order for the external count, and a shipped one with no
        // tracking for the missing-tracking count, or those two read zero
        // whether they were fixed or not.
        $this->order(['user_id' => $customer->id, 'is_manual' => true]);
        $manual = $this->order(['user_id' => $customer->id, 'is_manual' => true]);
        $untracked = $this->order(['user_id' => $customer->id, 'status' => 'shipped', 'tracking_number' => null]);

        $figures = function () use ($admin, $customer): array {
            $orders = $this->actingAs($admin)->get('/admin/orders')->assertOk();
            $dashboard = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
            $profile = $this->actingAs($admin)->get('/admin/customers/'.$customer->id)->assertOk();

            return [
                'kpi_amount' => $orders->viewData('kpis')['amount_cents'],
                'kpi_count' => $orders->viewData('kpis')['order_count'],
                'tab_count' => $orders->viewData('orderCount'),
                'net_revenue' => $dashboard->viewData('headline')['revenue_cents'],
                'dashboard_orders' => $dashboard->viewData('headline')['orders'],
                'spent' => $profile->viewData('spentCents'),
                'nav_badge' => Order::query()->awaitingStart()->count(),
                // Ces deux-là vivent désormais dans la bande « à traiter »,
                // qui retire les compteurs à zéro : absent vaut donc zéro.
                'to_prepare' => (int) ($dashboard->viewData('attention')->firstWhere('key', 'to-prepare')['count'] ?? 0),
                'missing_tracking' => (int) ($dashboard->viewData('attention')->firstWhere('key', 'missing-tracking')['count'] ?? 0),
                'external_customers' => $dashboard->viewData('reference')['external_orders'],
                'recent_orders' => $dashboard->viewData('recentOrders')->count(),
            ];
        };

        $this->assertSame(
            ['kpi_amount' => 4500, 'kpi_count' => 3, 'tab_count' => 3, 'net_revenue' => 4500,
                'dashboard_orders' => 3, 'spent' => 4500, 'nav_badge' => 2, 'to_prepare' => 2,
                'missing_tracking' => 1, 'external_customers' => 2, 'recent_orders' => 3],
            $figures(),
        );

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-test'), ['order_ids' => [$manual->id, $untracked->id]])
            ->assertRedirect();

        $this->assertSame(
            ['kpi_amount' => 1500, 'kpi_count' => 1, 'tab_count' => 1, 'net_revenue' => 1500,
                'dashboard_orders' => 1, 'spent' => 1500, 'nav_badge' => 1, 'to_prepare' => 1,
                'missing_tracking' => 0, 'external_customers' => 1, 'recent_orders' => 1],
            $figures(),
            'Every figure must drop by the marked orders — a missing one means a call site was overlooked.',
        );
    }
}
