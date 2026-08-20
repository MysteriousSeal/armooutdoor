<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(User $user, string $status = 'placed', ?Carbon $archivedAt = null): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
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

    public function test_contact_page_is_public(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Nous contacter');
    }

    public function test_contact_form_is_wired_for_dynamic_submission(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('id="contact-form"', false)
            ->assertSee('novalidate', false)
            ->assertSee('js/contact-form.js', false);
    }

    public function test_a_guest_can_send_a_message(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une commande',
            'message' => 'Bonjour, où en est ma commande ?',
        ]);

        $response->assertRedirect('/contact');
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une commande',
            'user_id' => null,
        ]);
    }

    public function test_a_dynamic_submission_receives_a_json_success_response(): void
    {
        $response = $this->postJson('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une commande',
            'message' => 'Bonjour, où en est ma commande ?',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertDatabaseHas('contact_messages', ['email' => 'jean@example.com']);
    }

    public function test_a_dynamic_submission_receives_json_validation_errors(): void
    {
        $response = $this->postJson('/contact', [
            'name' => 'Jean Martin',
            'email' => 'not-an-email',
            'subject' => 'Question',
            'message' => '',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_logged_in_customer_message_is_linked_to_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/contact', [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Retour produit',
            'message' => 'Comment retourner un article ?',
        ]);

        $this->assertDatabaseHas('contact_messages', [
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
    }

    public function test_message_is_required(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => '',
        ]);

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_filled_honeypot_field_rejects_the_submission(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'spam',
            'message' => 'spam',
            'website' => 'https://spam.example.com',
        ]);

        $response->assertSessionHasErrors('website');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_logged_in_customer_with_orders_sees_the_order_dropdown(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->get('/contact')
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_a_guest_does_not_see_the_order_dropdown(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertDontSee('name="order_id"', false);
    }

    public function test_a_customer_without_orders_does_not_see_the_order_dropdown(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/contact')
            ->assertDontSee('name="order_id"', false);
    }

    public function test_a_customer_can_reference_their_own_order(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        $this->actingAs($user)->post('/contact', [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Question',
            'message' => 'Où en est ma commande ?',
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('contact_messages', [
            'email' => $user->email,
            'order_id' => $order->id,
        ]);
    }

    public function test_a_customer_cannot_reference_someone_elses_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = $this->orderFor($otherUser);

        $response = $this->actingAs($user)->post('/contact', [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Question',
            'message' => 'Test',
            'order_id' => $otherOrder->id,
        ]);

        $response->assertSessionHasErrors('order_id');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_customer_cannot_reference_a_draft_or_archived_order(): void
    {
        $user = User::factory()->create();
        $draftOrder = $this->orderFor($user, 'draft');
        $archivedOrder = $this->orderFor($user, 'placed', now());

        foreach ([$draftOrder, $archivedOrder] as $order) {
            $response = $this->actingAs($user)->post('/contact', [
                'name' => $user->name,
                'email' => $user->email,
                'subject' => 'Question',
                'message' => 'Test',
                'order_id' => $order->id,
            ]);

            $response->assertSessionHasErrors('order_id');
        }

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_guest_cannot_reference_an_order(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        $response = $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Test',
            'order_id' => $order->id,
        ]);

        $response->assertSessionHasErrors('order_id');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_admin_message_view_links_the_referenced_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);
        $message = ContactMessage::query()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/messages/'.$message->id)
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_non_admins_cannot_view_the_admin_message_inbox(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get('/admin/messages')
            ->assertRedirect('/admin');
    }

    public function test_admin_can_view_and_read_a_message(): void
    {
        $admin = User::factory()->admin()->create();
        $message = ContactMessage::query()->create([
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour !',
        ]);

        $this->actingAs($admin)
            ->get('/admin/messages')
            ->assertOk()
            ->assertSee('Jean Martin');

        $this->assertNull($message->fresh()->read_at);

        $this->actingAs($admin)
            ->get('/admin/messages/'.$message->id)
            ->assertOk()
            ->assertSee('Bonjour !');

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_admin_nav_shows_unread_message_count(): void
    {
        $admin = User::factory()->admin()->create();
        ContactMessage::query()->create([
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour !',
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('admin-nav-badge', false);
    }
}
