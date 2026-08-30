<?php

namespace Tests\Feature\Admin;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Answering a customer from the back office: the thread view, the reply, and
 * the open/closed lifecycle around it.
 */
class AdminConversationTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(?User $user = null, string $body = 'Bonjour'): Conversation
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

    public function test_the_thread_renders_every_message_in_order(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create(), 'Première question');
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        $response = $this->actingAs($admin)->get(route('admin.conversations.show', $conversation));

        $response->assertOk()
            ->assertSee('Première question')
            ->assertSee('Notre réponse')
            ->assertSeeInOrder(['Première question', 'Notre réponse']);
    }

    public function test_opening_a_thread_marks_it_read(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->assertTrue($conversation->hasUnreadForAdmin());

        $this->actingAs($admin)->get(route('admin.conversations.show', $conversation))->assertOk();

        $this->assertFalse($conversation->fresh()->hasUnreadForAdmin());
    }

    public function test_an_admin_can_reply_to_a_customer_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $response = $this->actingAs($admin)->post(route('admin.conversations.reply', $conversation), [
            'body' => 'Votre commande part demain.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'author_type' => ConversationMessage::AUTHOR_ADMIN,
            'user_id' => $admin->id,
            'body' => 'Votre commande part demain.',
        ]);
        $this->assertNotNull($conversation->fresh()->last_admin_message_at);
    }

    public function test_a_reply_is_logged_to_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)->post(route('admin.conversations.reply', $conversation), [
            'body' => 'Bonjour',
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'conversation.replied',
            'user_id' => $admin->id,
            'subject_id' => $conversation->id,
        ]);
    }

    public function test_a_reply_body_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $conversation), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_a_dynamic_reply_returns_the_new_message_as_json(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $response = $this->actingAs($admin)->postJson(route('admin.conversations.reply', $conversation), [
            'body' => 'Bonjour',
        ]);

        $response->assertOk()->assertJsonStructure(['message', 'sentAt', 'authorLabel', 'body']);
        $this->assertSame(config('app.name'), $response->json('authorLabel'));
    }

    public function test_replying_to_a_guest_thread_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation();

        $this->assertTrue($conversation->isGuest());

        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $conversation), ['body' => 'Bonjour'])
            ->assertForbidden();

        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_a_guest_thread_shows_no_composer_only_an_email_fallback(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation();

        $this->actingAs($admin)
            ->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertDontSee('id="conversation-reply-form"', false)
            ->assertSee('Reply by email')
            ->assertSee('mailto:'.$conversation->email, false);
    }

    public function test_a_customer_thread_shows_the_composer(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)
            ->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('id="conversation-reply-form"', false)
            ->assertSee('js/conversation-reply.js', false);
    }

    public function test_an_admin_can_close_and_reopen_a_conversation(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)
            ->patch(route('admin.conversations.close', $conversation))
            ->assertRedirect();
        $this->assertTrue($conversation->fresh()->isClosed());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'conversation.closed']);

        $this->actingAs($admin)
            ->patch(route('admin.conversations.reopen', $conversation))
            ->assertRedirect();
        $this->assertTrue($conversation->fresh()->isOpen());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'conversation.reopened']);
    }

    public function test_a_closed_thread_offers_reopening_instead_of_a_composer(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $conversation->status = Conversation::STATUS_CLOSED;
        $conversation->save();

        $this->actingAs($admin)
            ->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            // Both the composer and the closed note are rendered — close and
            // reopen swap their visibility in place, without a reload. On a
            // closed thread the note stands open (no hidden attribute).
            ->assertSee('id="conversation-reply-form"', false)
            ->assertSee('id="conversation-closed-note" >', false)
            ->assertSee('Reopen to reply');
    }

    public function test_close_and_reopen_answer_in_json_for_the_in_place_toggle(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)
            ->patchJson(route('admin.conversations.close', $conversation))
            ->assertOk()
            ->assertJson(['message' => 'Conversation closed.', 'status' => 'closed']);

        $this->actingAs($admin)
            ->patchJson(route('admin.conversations.reopen', $conversation))
            ->assertOk()
            ->assertJson(['message' => 'Conversation reopened.', 'status' => 'open']);
    }

    public function test_the_sidebar_carries_the_customer_and_the_thread_facts(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $conversation = $this->conversation($customer);

        $this->actingAs($admin)
            ->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('View customer page')
            ->assertSee($customer->email)
            ->assertSee('Last activity');
    }

    public function test_the_inbox_defaults_to_open_conversations(): void
    {
        $admin = User::factory()->admin()->create();
        $open = $this->conversation(User::factory()->create(), 'Toujours ouverte');
        $closed = $this->conversation(User::factory()->create(), 'Déjà réglée');
        $closed->status = Conversation::STATUS_CLOSED;
        $closed->save();

        $this->actingAs($admin)
            ->get(route('admin.conversations.index'))
            ->assertOk()
            ->assertSee('Toujours ouverte')
            ->assertDontSee('Déjà réglée');
    }

    public function test_the_closed_tab_shows_only_closed_conversations(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conversation(User::factory()->create(), 'Toujours ouverte');
        $closed = $this->conversation(User::factory()->create(), 'Déjà réglée');
        $closed->status = Conversation::STATUS_CLOSED;
        $closed->save();

        $this->actingAs($admin)
            ->get(route('admin.conversations.index', ['tab' => 'closed']))
            ->assertOk()
            ->assertSee('Déjà réglée')
            ->assertDontSee('Toujours ouverte');
    }

    public function test_the_all_tab_shows_both(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conversation(User::factory()->create(), 'Toujours ouverte');
        $closed = $this->conversation(User::factory()->create(), 'Déjà réglée');
        $closed->status = Conversation::STATUS_CLOSED;
        $closed->save();

        $this->actingAs($admin)
            ->get(route('admin.conversations.index', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('Toujours ouverte')
            ->assertSee('Déjà réglée');
    }

    public function test_the_reply_never_shows_the_admins_own_name_to_the_thread(): void
    {
        $admin = User::factory()->admin()->create(['first_name' => 'Julie', 'last_name' => 'Simmons']);
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)->post(route('admin.conversations.reply', $conversation), [
            'body' => 'Bonjour',
        ]);

        $message = $conversation->messages()->where('author_type', ConversationMessage::AUTHOR_ADMIN)->firstOrFail();

        $this->assertSame(config('app.name'), $message->authorLabel());
        $this->assertSame($admin->id, $message->user_id);
    }

    public function test_the_activity_page_links_a_conversation_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)->post(route('admin.conversations.reply', $conversation), [
            'body' => 'Bonjour',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity'))
            ->assertOk()
            ->assertSee('Conversation')
            ->assertSee(route('admin.conversations.show', $conversation), false);
    }

    public function test_an_admin_can_edit_their_own_recent_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $message = $conversation->postMessage('Typo ici', ConversationMessage::AUTHOR_ADMIN, $admin);

        $response = $this->actingAs($admin)->patch(
            route('admin.conversations.messages.update', [$conversation, $message]),
            ['body' => 'Corrigé'],
        );

        $response->assertRedirect();
        $message->refresh();
        $this->assertSame('Corrigé', $message->body);
        $this->assertNotNull($message->edited_at);
        $this->assertTrue($message->wasEdited());
    }

    public function test_editing_is_logged_to_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $message = $conversation->postMessage('Typo', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->actingAs($admin)->patch(
            route('admin.conversations.messages.update', [$conversation, $message]),
            ['body' => 'Corrigé'],
        );

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'conversation.message_edited',
            'user_id' => $admin->id,
            'subject_id' => $conversation->id,
        ]);
    }

    public function test_an_admin_cannot_edit_a_reply_written_by_another_admin(): void
    {
        $author = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $message = $conversation->postMessage('Leur texte', ConversationMessage::AUTHOR_ADMIN, $author);

        $this->actingAs($other)
            ->patch(route('admin.conversations.messages.update', [$conversation, $message]), ['body' => 'Détourné'])
            ->assertForbidden();

        $this->assertSame('Leur texte', $message->fresh()->body);
    }

    public function test_an_admin_cannot_edit_a_customers_message(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $conversation = $this->conversation($customer);
        $message = $conversation->messages()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.conversations.messages.update', [$conversation, $message]), ['body' => 'Réécrit'])
            ->assertForbidden();

        $this->assertNull($message->fresh()->edited_at);
    }

    public function test_a_reply_can_no_longer_be_edited_after_the_window_closes(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $message = $conversation->postMessage('Trop tard', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->travel(ConversationMessage::EDIT_WINDOW_MINUTES + 1)->minutes();

        $this->actingAs($admin)
            ->patch(route('admin.conversations.messages.update', [$conversation, $message]), ['body' => 'Corrigé'])
            ->assertForbidden();

        $this->assertSame('Trop tard', $message->fresh()->body);
    }

    public function test_a_message_from_another_conversation_is_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $other = $this->conversation(User::factory()->create());
        $message = $other->postMessage('Ailleurs', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->actingAs($admin)
            ->patch(route('admin.conversations.messages.update', [$conversation, $message]), ['body' => 'Corrigé'])
            ->assertNotFound();
    }

    public function test_an_edited_reply_body_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $message = $conversation->postMessage('Original', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->actingAs($admin)
            ->patch(route('admin.conversations.messages.update', [$conversation, $message]), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame('Original', $message->fresh()->body);
    }

    public function test_the_edit_button_only_appears_on_the_admins_own_recent_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $mine = $conversation->postMessage('La mienne', ConversationMessage::AUTHOR_ADMIN, $admin);
        $theirs = $conversation->postMessage('La leur', ConversationMessage::AUTHOR_ADMIN, $other);

        $response = $this->actingAs($admin)->get(route('admin.conversations.show', $conversation));

        $response->assertOk()
            ->assertSee(route('admin.conversations.messages.update', [$conversation, $mine]), false)
            ->assertDontSee(route('admin.conversations.messages.update', [$conversation, $theirs]), false);
    }

    public function test_a_stale_reply_shows_no_edit_button(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $message = $conversation->postMessage('Ancienne', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->travel(ConversationMessage::EDIT_WINDOW_MINUTES + 1)->minutes();

        $this->actingAs($admin)
            ->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertDontSee('data-thread-edit', false);
    }

    public function test_a_dynamic_edit_returns_the_new_body_and_edited_label(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $message = $conversation->postMessage('Avant', ConversationMessage::AUTHOR_ADMIN, $admin);

        $response = $this->actingAs($admin)->patchJson(
            route('admin.conversations.messages.update', [$conversation, $message]),
            ['body' => 'Après'],
        );

        $response->assertOk()
            ->assertJsonStructure(['message', 'body', 'editedLabel'])
            ->assertJsonPath('body', 'Après');
    }

    public function test_a_fresh_reply_comes_back_with_its_edit_url(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($admin)
            ->postJson(route('admin.conversations.reply', $conversation), ['body' => 'Bonjour'])
            ->assertOk()
            ->assertJsonStructure(['editUrl']);
    }

    public function test_staff_can_answer_customers(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $this->actingAs($staff)
            ->post(route('admin.conversations.reply', $conversation), ['body' => 'Bonjour'])
            ->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', ['user_id' => $staff->id]);
    }
}
