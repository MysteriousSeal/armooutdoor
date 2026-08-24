<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archiving tidies the working list; it does not unmake a sale. So the money
 * figures and the order counts behind them span archived orders, while the
 * operational counts — what is left to pack, what is missing tracking — stay
 * on the working list where archiving means "done with this one".
 *
 * Test orders are the only orders left out of the money, since those sales
 * never happened at all.
 */
class ArchivedOrderFiguresTest extends TestCase
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

    public function test_archiving_an_order_leaves_the_money_figures_untouched(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();
        $this->order();

        $money = function () use ($admin): array {
            $orders = $this->actingAs($admin)->get('/admin/orders')->assertOk();
            $dashboard = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

            return [
                'kpi_amount' => $orders->viewData('kpis')['amount_cents'],
                'kpi_order_count' => $orders->viewData('kpis')['order_count'],
                'net_revenue' => $dashboard->viewData('headline')['revenue_cents'],
                'dashboard_orders' => $dashboard->viewData('headline')['orders'],
                'average_order' => $dashboard->viewData('headline')['average_order_cents'],
            ];
        };

        $before = $money();
        $this->actingAs($admin)->patch(route('admin.orders.archive', $order))->assertRedirect();

        $this->assertSame($before, $money(), 'Archiving is a tidying act, not a reversal of the sale.');
        $this->assertSame(3000, $before['kpi_amount']);
    }

    public function test_the_operational_counts_still_drop_when_an_order_is_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();
        $this->order();

        $counts = function () use ($admin): array {
            $orders = $this->actingAs($admin)->get('/admin/orders')->assertOk();
            $dashboard = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

            return [
                'kpi_to_prepare' => $orders->viewData('kpis')['to_prepare_count'],
                'dashboard_to_prepare' => (int) ($dashboard->viewData('attention')->firstWhere('key', 'to-prepare')['count'] ?? 0),
                'nav_badge' => Order::query()->awaitingStart()->count(),
                'orders_tab' => $orders->viewData('orderCount'),
            ];
        };

        $this->assertSame(
            ['kpi_to_prepare' => 2, 'dashboard_to_prepare' => 2, 'nav_badge' => 2, 'orders_tab' => 2],
            $counts(),
        );

        $this->actingAs($admin)->patch(route('admin.orders.archive', $order))->assertRedirect();

        // Archiving is how an admin says they are done with an order, so the
        // work queues must honour it even though the money does not.
        $this->assertSame(
            ['kpi_to_prepare' => 1, 'dashboard_to_prepare' => 1, 'nav_badge' => 1, 'orders_tab' => 1],
            $counts(),
        );
    }

    public function test_an_archived_test_order_is_still_left_out_of_the_money(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order();
        $archivedTest = $this->order(['archived_at' => now(), 'test_marked_at' => now()]);

        // Archived stops mattering; test never does.
        $orders = $this->actingAs($admin)->get('/admin/orders')->assertOk();
        $this->assertSame(1500, $orders->viewData('kpis')['amount_cents']);
        $this->assertSame(1, $orders->viewData('kpis')['order_count']);

        $dashboard = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->assertSame(1500, $dashboard->viewData('headline')['revenue_cents']);
        $this->assertFalse($dashboard->viewData('recentOrders')->contains('id', $archivedTest->id));
    }

    public function test_the_revenue_chart_and_marketplace_breakdown_cover_archived_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order(['archived_at' => now(), 'is_manual' => true, 'marketplace_name' => 'Rakuten']);

        $dashboard = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $today = $dashboard->viewData('revenueSeries')['current']->last();
        $this->assertSame(1500, $today['revenue_cents']);
        $this->assertSame(1, $today['orders']);

        $this->assertSame(
            1500,
            (int) ($dashboard->viewData('channelSplit')->firstWhere('label', 'Rakuten')['revenue_cents'] ?? 0),
        );
    }

    public function test_the_tab_counts_add_up_to_the_kpi_above_them(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order();
        $this->order(['archived_at' => now()]);
        $this->order(['archived_at' => now(), 'status' => 'draft']);
        $this->order(['status' => 'draft']);
        $this->order(['test_marked_at' => now()]);

        $page = $this->actingAs($admin)->get('/admin/orders')->assertOk();

        // Archived once swept up archived drafts as well, so Orders plus
        // Archived came to more than the KPI counted and there was no way to
        // tell from the page why. Drafts now sit apart wherever they are.
        $this->assertSame(1, $page->viewData('orderCount'));
        $this->assertSame(1, $page->viewData('archivedCount'));
        $this->assertSame(2, $page->viewData('draftCount'), 'An archived draft is still a draft.');
        $this->assertSame(1, $page->viewData('testCount'));

        $this->assertSame(
            $page->viewData('kpis')['order_count'],
            $page->viewData('orderCount') + $page->viewData('archivedCount'),
        );
    }

    public function test_an_archived_draft_is_listed_under_drafts_not_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = $this->order(['archived_at' => now(), 'status' => 'draft']);

        $this->actingAs($admin)->get('/admin/orders?tab=draft')->assertOk()->assertSee($draft->number);
        $this->actingAs($admin)->get('/admin/orders?tab=archived')->assertOk()->assertDontSee($draft->number);
    }

    public function test_a_customers_spend_and_their_listed_orders_describe_the_same_set(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $open = $this->order(['user_id' => $customer->id]);
        $archived = $this->order(['user_id' => $customer->id, 'archived_at' => now()]);

        $profile = $this->actingAs($admin)->get('/admin/customers/'.$customer->id)->assertOk();

        // The archived order counts, so it has to be visible — a total that
        // includes a row the page does not show cannot be checked by anyone.
        $profile->assertSee($open->number)->assertSee($archived->number)->assertSee('Archived');
        $this->assertSame(3000, $profile->viewData('spentCents'));

        $list = $this->actingAs($admin)->get('/admin/customers')->assertOk();
        $row = $list->viewData('customers')->firstWhere('id', $customer->id);
        $this->assertSame(2, $row->orders_count);
        $this->assertSame(3000, (int) $row->spent_cents);
    }
}
