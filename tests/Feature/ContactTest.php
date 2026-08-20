<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
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

    /**
     * A conversation plus its opening customer message, which is what a
     * contact-form submission produces.
     */
    private function conversation(array $attributes = []): Conversation
    {
        $body = $attributes['message'] ?? 'Test';
        unset($attributes['message']);

        $conversation = Conversation::query()->create([
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            ...$attributes,
        ]);

        $conversation->postMessage($body, ConversationMessage::AUTHOR_CUSTOMER, $conversation->user);

        return $conversation;
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
        $this->assertDatabaseHas('conversations', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une commande',
            'user_id' => null,
        ]);
    }

    public function test_a_submission_opens_a_thread_holding_the_message_body(): void
    {
        $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une commande',
            'message' => 'Bonjour, où en est ma commande ?',
        ]);

        $conversation = Conversation::query()->firstOrFail();

        $this->assertTrue($conversation->isOpen());
        $this->assertTrue($conversation->isGuest());
        $this->assertNotNull($conversation->last_customer_message_at);
        $this->assertTrue($conversation->hasUnreadForAdmin());

        $this->assertCount(1, $conversation->messages);
        $this->assertSame('Bonjour, où en est ma commande ?', $conversation->messages->first()->body);
        $this->assertSame(ConversationMessage::AUTHOR_CUSTOMER, $conversation->messages->first()->author_type);
    }

    public function test_a_logged_in_submission_records_the_author_on_the_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/contact', [
            'subject' => 'Question',
            'message' => 'Bonjour',
        ]);

        $conversation = Conversation::query()->firstOrFail();

        $this->assertFalse($conversation->isGuest());
        $this->assertSame($user->id, $conversation->messages->first()->user_id);
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
        $this->assertDatabaseHas('conversations', ['email' => 'jean@example.com']);
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
        $this->assertDatabaseCount('conversations', 0);
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

        $this->assertDatabaseHas('conversations', [
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
    }

    public function test_name_field_is_disabled_for_a_logged_in_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/contact')
            ->assertOk()
            ->assertSee('disabled', false)
            ->assertSee(__('store.contact_name_locked'));
    }

    public function test_name_field_is_enabled_for_a_guest(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertDontSee(__('store.contact_name_locked'));
    }

    public function test_a_logged_in_customer_cannot_override_their_name(): void
    {
        $user = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Martin']);

        $this->actingAs($user)->post('/contact', [
            'name' => 'Fake Name',
            'email' => $user->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'name' => $user->name,
        ]);
        $this->assertDatabaseMissing('conversations', ['name' => 'Fake Name']);
    }

    public function test_a_logged_in_customer_can_submit_without_a_name_field(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/contact', [
            'email' => $user->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $response->assertSessionDoesntHaveErrors('name');
        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'name' => $user->name,
        ]);
    }

    public function test_name_is_required_for_a_guest(): void
    {
        $response = $this->post('/contact', [
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_email_field_is_disabled_for_a_logged_in_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/contact')
            ->assertOk()
            ->assertSee('disabled', false)
            ->assertSee(__('store.contact_email_locked'));
    }

    public function test_email_field_is_enabled_for_a_guest(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertDontSee(__('store.contact_email_locked'));
    }

    public function test_a_logged_in_customer_cannot_override_their_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/contact', [
            'name' => $user->name,
            'email' => 'fake@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $this->assertDatabaseMissing('conversations', ['email' => 'fake@example.com']);
    }

    public function test_a_logged_in_customer_can_submit_without_an_email_field(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/contact', [
            'name' => $user->name,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $response->assertSessionDoesntHaveErrors('email');
        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_email_is_required_for_a_guest(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jean Martin',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('conversations', 0);
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
        $this->assertDatabaseCount('conversations', 0);
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
        $this->assertDatabaseCount('conversations', 0);
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

        $this->assertDatabaseHas('conversations', [
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
        $this->assertDatabaseCount('conversations', 0);
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

        $this->assertDatabaseCount('conversations', 0);
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
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_admin_message_view_links_the_referenced_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);
        $message = $this->conversation([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations/'.$message->id)
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_admin_message_list_links_the_known_customer_and_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);
        $this->conversation([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $response = $this->actingAs($admin)->get('/admin/conversations');

        $response->assertOk();
        $response->assertSee(
            'href="'.route('admin.customers.show', $customer).'" class="admin-table-link"',
            false,
        );
        $response->assertSee(
            'href="'.route('admin.orders.show', $order).'" class="admin-table-link"',
            false,
        );
    }

    public function test_index_shows_possibly_customer_for_a_guest_message_matching_an_email(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'jean@example.com']);
        $this->conversation([
            'name' => 'Jean M.',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $response = $this->actingAs($admin)->get('/admin/conversations');

        $response->assertOk();
        $response->assertSee('possibly '.$customer->name);
        $response->assertSee(
            'href="'.route('admin.customers.show', $customer).'" class="admin-message-guess"',
            false,
        );
    }

    public function test_index_matches_guest_email_case_insensitively(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'jean@example.com']);
        $this->conversation([
            'name' => 'Jean M.',
            'email' => 'JEAN@EXAMPLE.COM',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations')
            ->assertSee('possibly '.$customer->name);
    }

    public function test_index_shows_no_guess_when_no_email_matches(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conversation([
            'name' => 'Jean M.',
            'email' => 'nobody@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations')
            ->assertDontSee('possibly');
    }

    public function test_index_never_guesses_for_a_message_already_linked_to_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'jean@example.com']);
        $this->conversation([
            'user_id' => $customer->id,
            'name' => 'Jean M.',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations')
            ->assertDontSee('possibly');
    }

    public function test_show_page_links_the_possible_customer_for_a_guest_message(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'jean@example.com']);
        $message = $this->conversation([
            'name' => 'Jean M.',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations/'.$message->id)
            ->assertOk()
            ->assertSee('Possibly '.$customer->name)
            ->assertSee(route('admin.customers.show', $customer));
    }

    public function test_possible_customer_model_helper_ignores_admin_accounts(): void
    {
        User::factory()->admin()->create(['email' => 'jean@example.com']);
        $message = $this->conversation([
            'name' => 'Jean M.',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->assertNull($message->possibleCustomer());
    }

    public function test_index_shows_message_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $read = $this->conversation([
            'name' => 'Jean M.',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Test',
        ]);
        $read->markReadForAdmin();
        $this->conversation([
            'name' => 'Marie D.',
            'email' => 'marie@example.com',
            'subject' => 'Autre question',
            'message' => 'Test',
        ]);

        $response = $this->actingAs($admin)->get('/admin/conversations');

        $response->assertOk()
            ->assertSee('Total messages')
            ->assertSee('Unread')
            ->assertSee('Last 7 days')
            ->assertSee('admin-stat-card--warning', false);
    }

    public function test_index_shows_sender_initials_and_a_message_snippet(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conversation([
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour, je voudrais savoir si vous livrez en Corse.',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations')
            ->assertOk()
            ->assertSee('JM')
            ->assertSee('Bonjour, je voudrais savoir si vous livrez en Corse.');
    }

    public function test_index_marks_an_admin_sender_with_a_chip_and_no_link(): void
    {
        $admin = User::factory()->admin()->create();
        $sender = User::factory()->admin()->create(['first_name' => 'Sender', 'last_name' => 'Admin']);
        $this->conversation([
            'user_id' => $sender->id,
            'name' => $sender->name,
            'email' => $sender->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $response = $this->actingAs($admin)->get('/admin/conversations');

        $response->assertOk()
            ->assertSee('admin-role-chip', false)
            ->assertSee($sender->name)
            ->assertDontSee('href="'.route('admin.customers.show', $sender).'"', false);
    }

    public function test_index_still_links_a_regular_customer_sender(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->conversation([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations')
            ->assertOk()
            ->assertDontSee('admin-role-chip', false)
            ->assertSee('href="'.route('admin.customers.show', $customer).'"', false);
    }

    public function test_show_page_marks_an_admin_sender_without_a_broken_customer_link(): void
    {
        $admin = User::factory()->admin()->create();
        $sender = User::factory()->admin()->create();
        $message = $this->conversation([
            'user_id' => $sender->id,
            'name' => $sender->name,
            'email' => $sender->email,
            'subject' => 'Question',
            'message' => 'Test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations/'.$message->id)
            ->assertOk()
            ->assertSee('admin-message-link-chip--admin', false)
            ->assertDontSee('Customer account')
            ->assertDontSee(route('admin.customers.show', $sender), false);
    }

    public function test_the_customer_page_really_would_404_for_an_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $sender = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $sender))
            ->assertNotFound();
    }

    public function test_non_admins_cannot_view_the_admin_message_inbox(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get('/admin/conversations')
            ->assertRedirect('/admin');
    }

    public function test_admin_can_view_and_read_a_message(): void
    {
        $admin = User::factory()->admin()->create();
        $message = $this->conversation([
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour !',
        ]);

        $this->actingAs($admin)
            ->get('/admin/conversations')
            ->assertOk()
            ->assertSee('Jean Martin');

        $this->assertTrue($message->fresh()->hasUnreadForAdmin());

        $this->actingAs($admin)
            ->get('/admin/conversations/'.$message->id)
            ->assertOk()
            ->assertSee('Bonjour !');

        $this->assertFalse($message->fresh()->hasUnreadForAdmin());
    }

    public function test_admin_nav_shows_unread_message_count(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conversation([
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
