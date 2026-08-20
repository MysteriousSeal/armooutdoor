<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Archiving several orders at once. The batch is driven by explicit ids sent
 * from the page, never by re-running the list's filters, so nothing can be
 * archived that the admin did not tick.
 */
class AdminBulkArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'placed', ?Carbon $archivedAt = null): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'archived_at' => $archivedAt,
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

    public function test_several_orders_are_archived_in_one_request(): void
    {
        $admin = User::factory()->admin()->create();
        $a = $this->order();
        $b = $this->order();
        $untouched = $this->order();

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertNotNull($a->fresh()->archived_at);
        $this->assertNotNull($b->fresh()->archived_at);
        $this->assertNull($untouched->fresh()->archived_at, 'Only ticked orders should be archived.');
    }

    public function test_each_order_gets_its_own_activity_log_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $a = $this->order();
        $b = $this->order();

        $this->actingAs($admin)->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$a->id, $b->id]]);

        // One combined entry would break "who archived this order" for all
        // but the first, and the activity page links each entry to a subject.
        foreach ([$a, $b] as $order) {
            $this->assertDatabaseHas('admin_activity_logs', [
                'action' => 'order.archived',
                'user_id' => $admin->id,
                'subject_id' => $order->id,
            ]);
        }
    }

    public function test_several_orders_are_unarchived_in_one_request(): void
    {
        $admin = User::factory()->admin()->create();
        $a = $this->order('placed', now());
        $b = $this->order('placed', now());

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-unarchive'), ['order_ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertNull($a->fresh()->archived_at);
        $this->assertNull($b->fresh()->archived_at);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'order.unarchived', 'subject_id' => $a->id]);
    }

    public function test_an_order_archived_by_someone_else_is_skipped_and_the_rest_succeed(): void
    {
        $admin = User::factory()->admin()->create();
        $alreadyArchived = $this->order('placed', now()->subHour());
        $pending = $this->order();

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$alreadyArchived->id, $pending->id]])
            ->assertRedirect()
            // Reports what actually changed, not what was submitted.
            ->assertSessionHas('status', '1 order archived.');

        $this->assertNotNull($pending->fresh()->archived_at);
        $this->assertSame(
            $alreadyArchived->archived_at->timestamp,
            $alreadyArchived->fresh()->archived_at->timestamp,
            'An already-archived order should be left exactly as it was.',
        );
    }

    public function test_the_count_is_pluralised(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$this->order()->id]])
            ->assertSessionHas('status', '1 order archived.');

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$this->order()->id, $this->order()->id]])
            ->assertSessionHas('status', '2 orders archived.');
    }

    public function test_archiving_only_already_archived_orders_says_so(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('placed', now());

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$order->id]])
            ->assertSessionHas('status', 'Nothing to archive.');
    }

    public function test_drafts_are_passed_over_by_bulk_archiving(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = $this->order('draft');
        $real = $this->order();

        // A draft records nothing that happened, so there is nothing to file
        // away. Drafts are deleted instead, and a batch containing one still
        // archives the rest.
        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$draft->id, $real->id]])
            ->assertSessionHas('status', '1 order archived.');

        $this->assertNull($draft->fresh()->archived_at);
        $this->assertNotNull($real->fresh()->archived_at);
    }

    public function test_an_empty_selection_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => []])
            ->assertSessionHasErrors('order_ids');
    }

    public function test_an_unknown_id_is_refused_without_archiving_the_rest(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$order->id, 999999]])
            ->assertSessionHasErrors('order_ids.1');

        $this->assertNull($order->fresh()->archived_at);
    }

    public function test_an_oversized_selection_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => range(1, 101)])
            ->assertSessionHasErrors('order_ids');
    }

    public function test_a_non_admin_cannot_bulk_archive(): void
    {
        $customer = User::factory()->create();
        $order = $this->order();

        $this->actingAs($customer)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$order->id]])
            ->assertRedirect('/admin');

        $this->assertNull($order->fresh()->archived_at);
    }

    public function test_the_bulk_routes_are_not_swallowed_by_the_order_binding(): void
    {
        $admin = User::factory()->admin()->create();

        // /orders/bulk/archive must not resolve "bulk" as an order number.
        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$this->order()->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_the_list_renders_checkboxes_and_the_right_bulk_action(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('bulk-select-row', false)
            ->assertSee('value="'.$order->id.'"', false)
            ->assertSee(route('admin.orders.bulk-archive'), false)
            ->assertSee('js/admin-bulk-select.js', false);
    }

    public function test_a_failed_bulk_action_tells_the_admin_why(): void
    {
        $admin = User::factory()->admin()->create();

        // A bulk action has no field to hang an @error on, so without the
        // layout's banner this redirected back showing nothing at all.
        $this->actingAs($admin)
            ->from('/admin/orders')
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [999999]])
            ->assertRedirect('/admin/orders');

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('flash-error', false);
    }

    public function test_a_successful_bulk_action_shows_no_error_banner(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/orders')
            ->patch(route('admin.orders.bulk-archive'), ['order_ids' => [$this->order()->id]]);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('flash-success', false)
            ->assertDontSee('flash-error', false);
    }

    public function test_the_bulk_action_has_its_own_modal_separate_from_the_per_row_one(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order();

        $content = $this->actingAs($admin)->get('/admin/orders')->assertOk()->getContent();

        // Sharing the per-row modal meant reassigning its submit handler; a
        // close path that failed to clear it would have let a later per-row
        // click submit a stale bulk selection.
        $this->assertStringContainsString('id="bulk-confirm-modal"', $content);
        $this->assertStringContainsString('id="row-confirm-modal"', $content);
        $this->assertStringContainsString('id="bulk-confirm-form"', $content);
        $this->assertStringContainsString('id="row-confirm-form"', $content);
    }

    public function test_the_bulk_form_is_not_nested_inside_the_orders_table(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order();

        $content = $this->actingAs($admin)->get('/admin/orders')->assertOk()->getContent();

        // The row actions are already forms. If the bulk form wrapped the
        // table, the browser would silently drop them.
        $tableStart = strpos($content, '<table');
        $tableEnd = strpos($content, '</table>');
        $bulkForm = strpos($content, 'id="bulk-confirm-form"');

        $this->assertNotFalse($bulkForm);
        $this->assertTrue(
            $bulkForm > $tableEnd || $bulkForm < $tableStart,
            'The bulk form must sit outside the table.',
        );
    }

    public function test_the_archived_tab_offers_unarchive_instead(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('placed', now());

        $this->actingAs($admin)
            ->get('/admin/orders?tab=archived')
            ->assertOk()
            ->assertSee(route('admin.orders.bulk-unarchive'), false)
            ->assertDontSee(route('admin.orders.bulk-archive'), false);
    }
}
