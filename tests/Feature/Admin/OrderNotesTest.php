<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Notes the admins leave on an order, under the status history. */
class OrderNotesTest extends TestCase
{
    use RefreshDatabase;

    private function order(?User $customer = null): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => ($customer ?? User::factory()->create())->id,
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

    private function staff(): User
    {
        return User::factory()->admin()->create(['role' => 'staff', 'first_name' => 'Camille']);
    }

    public function test_an_admin_can_add_a_note_and_it_shows_with_author_and_date(): void
    {
        $order = $this->order();
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.orders.notes.store', $order), ['body' => "Buyer asked to ship Monday\nCall first"])
            ->assertRedirect(route('admin.orders.show', $order).'#order-notes');

        $note = $order->notes()->firstOrFail();
        $this->assertSame($staff->id, $note->user_id);
        $this->assertTrue(AdminActivityLog::query()->where('action', 'order.note_added')->exists());

        $this->actingAs($staff)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Buyer asked to ship Monday<br />', false)
            ->assertSee('Camille');
    }

    public function test_the_notes_come_under_the_status_history_newest_first(): void
    {
        $order = $this->order();
        $admin = User::factory()->admin()->create();
        OrderNote::query()->create(['order_id' => $order->id, 'user_id' => $admin->id, 'body' => 'First note', 'created_at' => now()->subHour()]);
        OrderNote::query()->create(['order_id' => $order->id, 'user_id' => $admin->id, 'body' => 'Second note']);

        $html = $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, '>Notes</h3>'), strpos($html, '>Status history</h3>'));
        $this->assertLessThan(strpos($html, 'First note'), strpos($html, 'Second note'));
    }

    public function test_a_note_is_escaped(): void
    {
        $order = $this->order();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.orders.notes.store', $order), ['body' => '<script>alert(1)</script>']);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_an_empty_or_overlong_note_is_refused(): void
    {
        $order = $this->order();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.orders.notes.store', $order), ['body' => '   '])
            ->assertSessionHasErrors('body');
        $this->actingAs($admin)->post(route('admin.orders.notes.store', $order), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $order->notes()->count());
    }

    public function test_the_author_can_delete_their_note(): void
    {
        $order = $this->order();
        $staff = $this->staff();
        $note = $order->notes()->create(['user_id' => $staff->id, 'body' => 'Mine']);

        $this->actingAs($staff)->delete(route('admin.orders.notes.destroy', [$order, $note]))->assertRedirect();

        $this->assertModelMissing($note);
        $this->assertTrue(AdminActivityLog::query()->where('action', 'order.note_deleted')->exists());
    }

    /** Delete opens the backend's usual confirmation modal, quoting the note. */
    public function test_delete_asks_in_a_modal(): void
    {
        $order = $this->order();
        $admin = User::factory()->admin()->create();
        $note = $order->notes()->create(['user_id' => $admin->id, 'body' => 'Buyer asked to ship Monday']);

        $html = $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->getContent();

        $this->assertStringContainsString('data-modal-open="delete-note-'.$note->id.'"', $html);
        $this->assertStringContainsString('<dialog id="delete-note-'.$note->id.'" class="modal"', $html);
        $this->assertStringContainsString('“Buyer asked to ship Monday”', $html);
        $this->assertStringContainsString('action="'.route('admin.orders.notes.destroy', [$order, $note]).'"', $html);
        $this->assertStringNotContainsString("confirm('Delete this note?')", $html);
    }

    public function test_the_owner_can_delete_any_note(): void
    {
        $order = $this->order();
        $note = $order->notes()->create(['user_id' => $this->staff()->id, 'body' => 'Staff note']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.orders.notes.destroy', [$order, $note]))
            ->assertRedirect();

        $this->assertModelMissing($note);
    }

    /** Staff can delete their own notes, not another admin's. */
    public function test_staff_cannot_delete_someone_elses_note(): void
    {
        $order = $this->order();
        $note = $order->notes()->create(['user_id' => User::factory()->admin()->create()->id, 'body' => 'Owner note']);
        $staff = $this->staff();

        $this->actingAs($staff)->delete(route('admin.orders.notes.destroy', [$order, $note]))->assertForbidden();
        $this->assertModelExists($note);

        $this->actingAs($staff)->get(route('admin.orders.show', $order))
            ->assertSee('Owner note')
            ->assertDontSee(route('admin.orders.notes.destroy', [$order, $note]), false);
    }

    public function test_a_note_cannot_be_deleted_through_another_order(): void
    {
        $order = $this->order();
        $other = $this->order();
        $admin = User::factory()->admin()->create();
        $note = $order->notes()->create(['user_id' => $admin->id, 'body' => 'Here']);

        $this->actingAs($admin)->delete(route('admin.orders.notes.destroy', [$other, $note]))->assertNotFound();
        $this->assertModelExists($note);
    }

    /** Customers reach neither the notes nor the routes that write them. */
    public function test_a_customer_cannot_add_or_see_notes(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $order->notes()->create(['user_id' => User::factory()->admin()->create()->id, 'body' => 'Private remark']);

        $this->actingAs($customer)->post(route('admin.orders.notes.store', $order), ['body' => 'Hi'])->assertRedirect();
        $this->assertSame(1, $order->notes()->count());

        $this->actingAs($customer)->get(route('orders.show', $order))->assertDontSee('Private remark');
    }

    /** Deleting the author's account keeps the note, unsigned. */
    public function test_a_note_outlives_its_author(): void
    {
        $order = $this->order();
        $staff = $this->staff();
        $note = $order->notes()->create(['user_id' => $staff->id, 'body' => 'Kept']);

        $staff->delete();

        $this->assertNull($note->fresh()->user_id);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.show', $order))
            ->assertSee('Kept')
            ->assertSee('Deleted admin');
    }
}
