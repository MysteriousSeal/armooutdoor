<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The edges around the conversation system: what a hostile or clumsy body
 * does to the page, what the rate limits actually allow, and what happens
 * to a thread when the account behind it goes away.
 */
class ConversationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function threadFor(?User $user, string $body = 'Bonjour'): Conversation
    {
        $conversation = Conversation::query()->create([
            'user_id' => $user?->id,
            'name' => $user?->name ?? 'Jean Martin',
            'email' => $user?->email ?? 'jean@example.com',
            'subject' => 'Question',
        ]);

        $conversation->postMessage($body, ConversationMessage::AUTHOR_CUSTOMER, $user);

        return $conversation;
    }

    /*
     * Escaping — a message body is author-written text and must never reach
     * the page as markup, on either side.
     */

    public function test_a_message_body_is_escaped_in_the_customer_thread(): void
    {
        $user = User::factory()->create();
        $conversation = $this->threadFor($user, '<script>alert(1)</script>');

        $response = $this->actingAs($user)->get('/account/messages/'.$conversation->id);

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;', $response->getContent());
    }

    public function test_a_message_body_is_escaped_in_the_admin_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->threadFor(User::factory()->create(), '<img src=x onerror=alert(1)>');

        $response = $this->actingAs($admin)->get(route('admin.conversations.show', $conversation));

        $response->assertOk();
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $response->getContent());
        $this->assertStringContainsString('&lt;img', $response->getContent());
    }

    public function test_a_subject_is_escaped_in_the_admin_inbox(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->threadFor(User::factory()->create());
        $conversation->subject = '<b>gras</b>';
        $conversation->save();

        $response = $this->actingAs($admin)->get(route('admin.conversations.index'));

        $response->assertOk();
        $this->assertStringNotContainsString('<b>gras</b>', $response->getContent());
    }

    public function test_newlines_survive_as_line_breaks_without_letting_markup_through(): void
    {
        $user = User::factory()->create();
        $conversation = $this->threadFor($user, "Ligne un\n<b>Ligne deux</b>");

        $content = $this->actingAs($user)->get('/account/messages/'.$conversation->id)->getContent();

        $this->assertStringContainsString('<br />', $content);
        $this->assertStringNotContainsString('<b>Ligne deux</b>', $content);
    }

    /*
     * Rate limits.
     */

    public function test_the_customer_reply_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $conversation = $this->threadFor($user);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)
                ->post('/account/messages/'.$conversation->id.'/reply', ['body' => 'Message '.$i])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post('/account/messages/'.$conversation->id.'/reply', ['body' => 'Un de trop'])
            ->assertStatus(429);

        // The eleventh never landed.
        $this->assertSame(11, $conversation->messages()->count());
    }

    public function test_the_contact_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', [
                'name' => 'Jean Martin',
                'email' => 'jean@example.com',
                'subject' => 'Question '.$i,
                'message' => 'Bonjour',
            ])->assertRedirect();
        }

        $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Un de trop',
            'message' => 'Bonjour',
        ])->assertStatus(429);

        $this->assertDatabaseCount('conversations', 5);
    }

    /*
     * Length cap on replies.
     */

    public function test_an_admin_reply_is_capped_at_5000_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->threadFor(User::factory()->create());

        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $conversation), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_a_customer_reply_is_capped_at_5000_characters(): void
    {
        $user = User::factory()->create();
        $conversation = $this->threadFor($user);

        $this->actingAs($user)
            ->post('/account/messages/'.$conversation->id.'/reply', ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_a_reply_of_exactly_5000_characters_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->threadFor(User::factory()->create());

        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $conversation), ['body' => str_repeat('a', 5000)])
            ->assertRedirect();

        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_an_edited_reply_is_capped_at_5000_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->threadFor(User::factory()->create());
        $message = $conversation->postMessage('Court', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->actingAs($admin)
            ->patch(
                route('admin.conversations.messages.update', [$conversation, $message]),
                ['body' => str_repeat('a', 5001)],
            )
            ->assertSessionHasErrors('body');

        $this->assertSame('Court', $message->fresh()->body);
    }

    /*
     * Inbox pagination and empty states.
     */

    public function test_the_admin_inbox_paginates_at_twenty(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 22) as $i) {
            $this->threadFor(User::factory()->create(), 'Message '.$i);
        }

        $response = $this->actingAs($admin)->get(route('admin.conversations.index'));

        $response->assertOk();
        $this->assertCount(20, $response->viewData('conversations'));

        $this->actingAs($admin)
            ->get(route('admin.conversations.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('conversations', fn ($page): bool => count($page) === 2);
    }

    public function test_pagination_keeps_the_active_tab(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 22) as $i) {
            $this->threadFor(User::factory()->create(), 'Message '.$i);
        }

        $this->actingAs($admin)
            ->get(route('admin.conversations.index', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('tab=all', false);
    }

    public function test_each_admin_tab_has_its_own_empty_state(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.conversations.index'))
            ->assertOk()
            ->assertSee('Nothing waiting for a reply.');

        $this->actingAs($admin)
            ->get(route('admin.conversations.index', ['tab' => 'closed']))
            ->assertOk()
            ->assertSee('No closed conversations.');

        $this->actingAs($admin)
            ->get(route('admin.conversations.index', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('No messages yet.');
    }

    public function test_the_customer_inbox_has_an_empty_state_offering_a_new_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account/messages')
            ->assertOk()
            ->assertSee(__('store.conversations_empty'))
            ->assertSee(__('store.conversations_new'));
    }

    /*
     * A deleted customer.
     */

    public function test_deleting_a_customer_leaves_the_thread_as_a_guest_thread(): void
    {
        $user = User::factory()->create();
        $conversation = $this->threadFor($user, 'Ma question');

        $user->forceDelete();
        $conversation->refresh();

        // The thread and its history survive, but there is no longer an
        // account behind it — so it behaves exactly like a guest thread.
        $this->assertNull($conversation->user_id);
        $this->assertTrue($conversation->isGuest());
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertNull($conversation->messages()->first()->user_id);
    }

    public function test_an_admin_cannot_reply_to_a_thread_whose_customer_was_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->threadFor($user);

        $user->forceDelete();

        // Nobody left to read a reply, so the guest guard takes over.
        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $conversation->fresh()), ['body' => 'Bonjour'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.conversations.show', $conversation->fresh()))
            ->assertOk()
            ->assertDontSee('id="conversation-reply-form"', false)
            ->assertSee('Reply by email');
    }

    public function test_deleting_an_order_leaves_the_thread_intact(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
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
        ]);

        $conversation = $this->threadFor($user);
        $conversation->order_id = $order->id;
        $conversation->save();

        $order->delete();
        $conversation->refresh();

        $this->assertNull($conversation->order_id);
        $this->actingAs($admin)->get(route('admin.conversations.show', $conversation))->assertOk();
    }
}
