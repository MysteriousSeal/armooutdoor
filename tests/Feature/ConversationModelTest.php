<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The thread mechanics underneath the messages feature: how a message lands,
 * how unread state is derived from it, and how a closed thread comes back.
 */
class ConversationModelTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(?User $user = null): Conversation
    {
        return Conversation::query()->create([
            'user_id' => $user?->id,
            'name' => $user?->name ?? 'Jean Martin',
            'email' => $user?->email ?? 'jean@example.com',
            'subject' => 'Question',
        ]);
    }

    public function test_a_customer_message_sets_the_customer_timestamp_only(): void
    {
        $conversation = $this->conversation();

        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertNotNull($conversation->last_customer_message_at);
        $this->assertNull($conversation->last_admin_message_at);
    }

    public function test_an_admin_message_sets_the_admin_timestamp_only(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertNotNull($conversation->last_admin_message_at);
        $this->assertNull($conversation->last_customer_message_at);
    }

    public function test_a_customer_message_is_unread_for_the_admin_until_read(): void
    {
        $conversation = $this->conversation();
        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertTrue($conversation->hasUnreadForAdmin());

        $conversation->markReadForAdmin();

        $this->assertFalse($conversation->hasUnreadForAdmin());
    }

    public function test_a_second_customer_message_goes_unread_again(): void
    {
        $conversation = $this->conversation();
        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);
        $conversation->markReadForAdmin();

        $this->travel(1)->minutes();
        $conversation->postMessage('Une relance', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertTrue($conversation->hasUnreadForAdmin());
    }

    public function test_an_admin_reply_is_unread_for_the_customer_until_read(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertTrue($conversation->hasUnreadForCustomer());

        $conversation->markReadForCustomer();

        $this->assertFalse($conversation->hasUnreadForCustomer());
    }

    public function test_a_thread_with_no_messages_is_unread_for_nobody(): void
    {
        $conversation = $this->conversation();

        $this->assertFalse($conversation->hasUnreadForAdmin());
        $this->assertFalse($conversation->hasUnreadForCustomer());
    }

    public function test_an_admin_reply_does_not_make_the_thread_unread_for_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);
        $conversation->markReadForAdmin();

        $this->travel(1)->minutes();
        $conversation->postMessage('Notre réponse', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertFalse($conversation->hasUnreadForAdmin());
    }

    public function test_a_customer_reply_reopens_a_closed_thread(): void
    {
        $conversation = $this->conversation(User::factory()->create());
        $conversation->status = Conversation::STATUS_CLOSED;
        $conversation->save();

        $conversation->postMessage('Encore une question', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertTrue($conversation->isOpen());
    }

    public function test_an_admin_reply_does_not_reopen_a_closed_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $conversation->status = Conversation::STATUS_CLOSED;
        $conversation->save();

        $conversation->postMessage('Un dernier mot', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertTrue($conversation->isClosed());
    }

    public function test_a_thread_without_an_account_is_a_guest_thread(): void
    {
        $this->assertTrue($this->conversation()->isGuest());
        $this->assertFalse($this->conversation(User::factory()->create())->isGuest());
    }

    public function test_the_unread_for_admin_scope_matches_the_helper(): void
    {
        $unread = $this->conversation();
        $unread->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);

        $read = $this->conversation();
        $read->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);
        $read->markReadForAdmin();

        $this->conversation();

        $ids = Conversation::query()->unreadForAdmin()->pluck('id');

        $this->assertTrue($ids->contains($unread->id));
        $this->assertFalse($ids->contains($read->id));
        $this->assertCount(1, $ids);
    }

    public function test_an_admin_reply_is_attributed_to_the_shop_not_the_admin(): void
    {
        $admin = User::factory()->admin()->create(['first_name' => 'Julie', 'last_name' => 'Simmons']);
        $conversation = $this->conversation(User::factory()->create());

        $message = $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertSame(config('app.name'), $message->authorLabel());
        $this->assertNotSame($admin->name, $message->authorLabel());

        // The authoring admin is still recorded, for the audit trail.
        $this->assertSame($admin->id, $message->user_id);
    }

    public function test_a_customer_message_is_attributed_to_the_sender(): void
    {
        $conversation = $this->conversation();

        $message = $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertSame($conversation->name, $message->authorLabel());
    }

    public function test_an_admin_avatar_carries_the_shop_mark_not_the_staff_initials(): void
    {
        $admin = User::factory()->admin()->create(['first_name' => 'Julie', 'last_name' => 'Simmons']);
        $conversation = $this->conversation(User::factory()->create());

        $message = $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertSame('AO', $message->avatarInitials());
        $this->assertNotSame('JS', $message->avatarInitials());
    }

    public function test_a_customer_avatar_uses_the_thread_initials(): void
    {
        $conversation = $this->conversation();

        $message = $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertSame($conversation->initials(), $message->avatarInitials());
    }

    public function test_consecutive_messages_from_the_same_side_group_together(): void
    {
        $conversation = $this->conversation();
        $first = $conversation->postMessage('Un', ConversationMessage::AUTHOR_CUSTOMER);
        $second = $conversation->postMessage('Deux', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertFalse($first->continues(null));
        $this->assertTrue($second->continues($first));
    }

    public function test_a_message_from_the_other_side_starts_a_new_group(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());
        $customerMessage = $conversation->postMessage('Un', ConversationMessage::AUTHOR_CUSTOMER);
        $adminMessage = $conversation->postMessage('Deux', ConversationMessage::AUTHOR_ADMIN, $admin);

        $this->assertFalse($adminMessage->continues($customerMessage));
    }

    public function test_a_message_on_a_new_day_starts_a_new_group(): void
    {
        $conversation = $this->conversation();
        $first = $conversation->postMessage('Un', ConversationMessage::AUTHOR_CUSTOMER);

        $this->travel(1)->days();
        $second = $conversation->postMessage('Deux', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertFalse($second->continues($first));
    }

    public function test_messages_come_back_oldest_first(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $this->conversation(User::factory()->create());

        $conversation->postMessage('Premier', ConversationMessage::AUTHOR_CUSTOMER);
        $this->travel(1)->minutes();
        $conversation->postMessage('Deuxième', ConversationMessage::AUTHOR_ADMIN, $admin);
        $this->travel(1)->minutes();
        $conversation->postMessage('Troisième', ConversationMessage::AUTHOR_CUSTOMER);

        $this->assertSame(
            ['Premier', 'Deuxième', 'Troisième'],
            $conversation->messages()->get()->pluck('body')->all(),
        );
        $this->assertSame('Troisième', $conversation->latestMessage->body);
    }
}
