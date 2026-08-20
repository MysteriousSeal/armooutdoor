<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A draft records nothing that happened — nothing charged, nothing shipped,
 * no invoice number taken. There is no reason to file one away, so drafts are
 * deleted rather than archived, and everything else is archived rather than
 * deleted. Neither action is offered where it does not belong.
 */
class DraftOrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'placed', array $attributes = []): Order
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
            ...$attributes,
        ]);
    }

    public function test_a_draft_cannot_be_archived_or_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = $this->order('draft');
        $archivedDraft = $this->order('draft', ['archived_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.orders.archive', $draft))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.orders.unarchive', $archivedDraft))->assertForbidden();

        $this->assertNull($draft->fresh()->archived_at);
        $this->assertNotNull($archivedDraft->fresh()->archived_at);
    }

    public function test_bulk_unarchiving_passes_over_drafts(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = $this->order('draft', ['archived_at' => now()]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.bulk-unarchive'), ['order_ids' => [$draft->id]])
            ->assertSessionHas('status', 'Nothing to unarchive.');

        $this->assertNotNull($draft->fresh()->archived_at);
    }

    public function test_an_owner_deletes_a_draft_and_its_lines_go_with_it(): void
    {
        $owner = User::factory()->admin()->create();
        $draft = $this->order('draft');
        OrderItem::query()->create([
            'order_id' => $draft->id,
            'product_slug' => 'thing',
            'name' => ['fr' => 'Chose'],
            'image' => '',
            'unit_price_cents' => 1000,
            'quantity' => 1,
            'line_cents' => 1000,
        ]);

        $this->actingAs($owner)
            ->delete(route('admin.orders.destroy', $draft))
            ->assertRedirect(route('admin.orders.index', ['tab' => 'draft']));

        $this->assertDatabaseMissing('orders', ['id' => $draft->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $draft->id]);
    }

    public function test_an_order_that_is_not_a_draft_cannot_be_deleted(): void
    {
        $owner = User::factory()->admin()->create();

        foreach (['placed', 'preparing', 'shipped', 'refunded'] as $status) {
            $order = $this->order($status);

            // Everything past draft is a record of something that happened.
            $this->actingAs($owner)->delete(route('admin.orders.destroy', $order))->assertForbidden();
            $this->assertDatabaseHas('orders', ['id' => $order->id]);
        }
    }

    public function test_staff_cannot_delete_a_draft(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $draft = $this->order('draft');

        $this->actingAs($staff)->delete(route('admin.orders.destroy', $draft))->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.orders.bulk-destroy'), ['order_ids' => [$draft->id]])->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $draft->id]);
    }

    public function test_bulk_deleting_touches_only_drafts(): void
    {
        $owner = User::factory()->admin()->create();
        $draftA = $this->order('draft');
        $draftB = $this->order('draft');
        $real = $this->order();

        // A real order slipped into the selection must survive, and the count
        // must report what actually went rather than what was submitted.
        $this->actingAs($owner)
            ->delete(route('admin.orders.bulk-destroy'), ['order_ids' => [$draftA->id, $draftB->id, $real->id]])
            ->assertSessionHas('status', '2 orders deleted.');

        $this->assertDatabaseMissing('orders', ['id' => $draftA->id]);
        $this->assertDatabaseMissing('orders', ['id' => $draftB->id]);
        $this->assertDatabaseHas('orders', ['id' => $real->id]);
    }

    public function test_deleting_is_recorded_before_the_row_disappears(): void
    {
        $owner = User::factory()->admin()->create();
        $draft = $this->order('draft');
        $number = $draft->number;

        $this->actingAs($owner)->delete(route('admin.orders.destroy', $draft));

        // The order is gone, so the entry has to carry the number itself.
        $log = AdminActivityLog::query()->where('action', 'order.deleted')->firstOrFail();
        $this->assertStringContainsString($number, $log->description);
    }

    public function test_the_page_offers_delete_on_a_draft_and_archive_everywhere_else(): void
    {
        $owner = User::factory()->admin()->create();
        $draft = $this->order('draft');
        $real = $this->order();

        $this->actingAs($owner)->get('/admin/orders/'.$draft->number)->assertOk()
            ->assertSee('Delete draft')
            ->assertDontSee('data-modal-open="archive-confirm-modal"', false);

        $this->actingAs($owner)->get('/admin/orders/'.$real->number)->assertOk()
            ->assertSee('Archive')
            ->assertDontSee('Delete draft');
    }

    public function test_staff_are_not_offered_a_delete_they_cannot_perform(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $draft = $this->order('draft');

        $this->actingAs($staff)->get('/admin/orders/'.$draft->number)->assertOk()->assertDontSee('Delete draft');
        $this->actingAs($staff)->get('/admin/orders?tab=draft')->assertOk()->assertDontSee('Delete draft');
    }
}
