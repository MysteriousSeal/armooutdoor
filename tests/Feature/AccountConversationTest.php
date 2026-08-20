<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer's own view of a conversation: what they can reach, what they
 * can send, and — critically — what they are never shown about who replied.
 */
class AccountConversationTest extends TestCase
{
    use RefreshDatabase;

    private function conversationFor(User $user, string $body = 'Bonjour'): Conversation
    {
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Question',
        ]);

        $conversation->postMessage($body, ConversationMessage::AUTHOR_CUSTOMER, $user);

        return $conversation;
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/account/messages')->assertRedirect('/login');
    }

    public function test_a_customer_sees_their_own_threads(): void
    {
        $user = User::factory()->create();
        $this->conversationFor($user, 'Ma question à moi');

        $this->actingAs($user)
            ->get('/account/messages')
            ->assertOk()
            ->assertSee('Ma question à moi');
    }

    public function test_a_customer_does_not_see_another_customers_threads(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->conversationFor($other, 'La question de quelqu’un d’autre');

        $this->actingAs($user)
            ->get('/account/messages')
            ->assertOk()
            ->assertDontSee('La question de quelqu’un d’autre');
    }

    public function test_opening_another_customers_thread_is_not_found(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationFor($other);

        $this->actingAs($user)
            ->get('/account/messages/'.$conversation->id)
            ->assertNotFound();
    }

    public function test_replying_to_another_customers_thread_is_not_found(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationFor($other);

        $this->actingAs($user)
            ->post('/account/messages/'.$conversation->id.'/reply', ['body' => 'Coucou'])
            ->assertNotFound();

        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_a_guest_thread_cannot_be_claimed_by_a_signed_in_customer(): void
    {
        $user = User::factory()->create();
        $guestThread = Conversation::query()->create([
            'name' => 'Jean Martin',
            'email' => $user->email,
            'subject' => 'Question',
        ]);
        $guestThread->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);

        $this->actingAs($user)
            ->get('/account/messages/'.$guestThread->id)
            ->assertNotFound();
    }

    public function test_a_customer_can_reply_to_their_own_thread(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);

        $response = $this->actingAs($user)->post('/account/messages/'.$conversation->id.'/reply', [
            'body' => 'Merci pour votre réponse.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'author_type' => ConversationMessage::AUTHOR_CUSTOMER,
            'user_id' => $user->id,
            'body' => 'Merci pour votre réponse.',
        ]);
    }

    public function test_a_customer_reply_shows_up_unread_for_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->markReadForAdmin();

        $this->travel(1)->minutes();
        $this->actingAs($user)->post('/account/messages/'.$conversation->id.'/reply', ['body' => 'Une relance']);

        $this->assertTrue($conversation->fresh()->hasUnreadForAdmin());

        $this->actingAs($admin)
            ->get(route('admin.conversations.index'))
            ->assertOk()
            ->assertSee('Une relance');
    }

    public function test_a_reply_body_is_required(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);

        $this->actingAs($user)
            ->post('/account/messages/'.$conversation->id.'/reply', ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_a_customer_cannot_reply_to_a_closed_thread(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->status = Conversation::STATUS_CLOSED;
        $conversation->save();

        $this->actingAs($user)
            ->post('/account/messages/'.$conversation->id.'/reply', ['body' => 'Encore une chose'])
            ->assertForbidden();

        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_a_closed_thread_hides_the_composer(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->status = Conversation::STATUS_CLOSED;
        $conversation->save();

        $this->actingAs($user)
            ->get('/account/messages/'.$conversation->id)
            ->assertOk()
            ->assertDontSee('id="conversation-reply-form"', false)
            ->assertSee(__('store.conversation_closed_note'));
    }

    public function test_opening_a_thread_clears_the_customers_unread_state(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertTrue($conversation->hasUnreadForCustomer());

        $this->actingAs($user)->get('/account/messages/'.$conversation->id)->assertOk();

        $this->assertFalse($conversation->fresh()->hasUnreadForCustomer());
    }

    public function test_the_account_nav_shows_an_unread_badge(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('account-nav-badge', false);
    }

    public function test_the_account_nav_has_no_badge_without_a_reply(): void
    {
        $user = User::factory()->create();
        $this->conversationFor($user);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertDontSee('account-nav-badge', false);
    }

    public function test_the_account_hub_links_messages_with_a_thread_count(): void
    {
        $user = User::factory()->create();
        $this->conversationFor($user);
        $this->conversationFor($user);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee(__('store.account_conversations'))
            ->assertSee(route('account.conversations.index'), false)
            ->assertSee(trans_choice('store.conversation_count', 2, ['count' => 2]));
    }

    public function test_the_account_hub_surfaces_unread_replies_instead_of_the_count(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee(trans_choice('store.conversation_unread_count', 1, ['count' => 1]));
    }

    public function test_the_site_header_badges_the_name_when_a_reply_is_waiting(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        // Any storefront page, not just the account section.
        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('site-auth-badge', false);
    }

    public function test_the_site_header_has_no_badge_without_a_reply(): void
    {
        $user = User::factory()->create();
        $this->conversationFor($user);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertDontSee('site-auth-badge', false);
    }

    public function test_the_site_header_badge_clears_once_the_thread_is_opened(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->actingAs($user)->get('/account/messages/'.$conversation->id)->assertOk();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertDontSee('site-auth-badge', false);
    }

    public function test_guests_get_no_header_badge(): void
    {
        $this->get('/')->assertOk()->assertDontSee('site-auth-badge', false);
    }

    public function test_the_timeline_renders_avatars_a_day_separator_and_relative_times(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        $response = $this->actingAs($user)->get('/account/messages/'.$conversation->id);

        $response->assertOk()
            ->assertSee('thread-avatar', false)
            ->assertSee('thread-day', false)
            ->assertSee($conversation->created_at->translatedFormat('j F Y'))
            ->assertSee('il y a');
    }

    public function test_a_grouped_message_renders_a_blank_spacer_not_a_second_avatar(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user, 'Premier');
        $conversation->postMessage('Test', ConversationMessage::AUTHOR_CUSTOMER, $user);

        $response = $this->actingAs($user)->get('/account/messages/'.$conversation->id);

        $response->assertOk()->assertSee('thread-avatar--placeholder', false);

        // The initials appear once, on the first message of the run — the
        // follow-up gets an empty spacer rather than a repeated avatar.
        $this->assertSame(
            1,
            substr_count($response->getContent(), '>'.$conversation->initials().'</span>'),
        );
    }

    public function test_the_thread_page_keeps_the_account_nav(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);

        $this->actingAs($user)
            ->get('/account/messages/'.$conversation->id)
            ->assertOk()
            ->assertSee('account-nav', false)
            ->assertSee(__('store.account_orders'));
    }

    public function test_an_admin_reply_is_shown_as_the_shop_never_the_staff_member(): void
    {
        $admin = User::factory()->admin()->create([
            'first_name' => 'Julie',
            'last_name' => 'Simmons',
            'email' => 'julie@armooutdoor.test',
        ]);
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);
        $conversation->postMessage('Votre commande part demain.', ConversationMessage::AUTHOR_ADMIN, $admin);

        $response = $this->actingAs($user)->get('/account/messages/'.$conversation->id);

        $response->assertOk()
            ->assertSee('Votre commande part demain.')
            ->assertSee(config('app.name'))
            ->assertDontSee('Julie')
            ->assertDontSee('Simmons')
            ->assertDontSee('julie@armooutdoor.test');
    }

    public function test_the_thread_links_a_referenced_order(): void
    {
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

        $conversation = $this->conversationFor($user);
        $conversation->order_id = $order->id;
        $conversation->save();

        $this->actingAs($user)
            ->get('/account/messages/'.$conversation->id)
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_a_dynamic_reply_returns_the_new_message_as_json(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user);

        $response = $this->actingAs($user)->postJson('/account/messages/'.$conversation->id.'/reply', [
            'body' => 'Merci',
        ]);

        $response->assertOk()->assertJsonStructure(['message', 'sentAt', 'authorLabel', 'body']);
        $this->assertSame($conversation->name, $response->json('authorLabel'));
    }
}
