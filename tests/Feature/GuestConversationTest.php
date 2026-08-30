<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\ConversationReplied;
use App\Notifications\GuestConversationStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A guest's thread lives behind a private emailed link: minted when they
 * write, mailed on every reply, readable for a month past closure, and
 * never confirming anything to a token it doesn't recognise.
 */
class GuestConversationTest extends TestCase
{
    use RefreshDatabase;

    private function guestConversation(array $overrides = []): Conversation
    {
        $conversation = Conversation::query()->create([
            'name' => 'Jean Passant',
            'email' => 'jean.passant@example.com',
            'subject' => 'Question de passage',
            ...$overrides,
        ]);
        $conversation->postMessage('Bonjour, une question.', ConversationMessage::AUTHOR_CUSTOMER, null);

        return $conversation->fresh();
    }

    public function test_writing_as_a_guest_mints_a_token_and_emails_the_link(): void
    {
        Notification::fake();

        $this->post('/contact', [
            'name' => 'Jean Passant',
            'email' => 'jean.passant@example.com',
            'subject' => 'Question de passage',
            'message' => 'Bonjour, une question.',
        ])->assertRedirect();

        $conversation = Conversation::query()->sole();
        $this->assertNotNull($conversation->guest_token);

        Notification::assertSentOnDemand(
            GuestConversationStarted::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'jean.passant@example.com',
        );
    }

    public function test_the_private_page_opens_by_token_and_404s_otherwise(): void
    {
        $conversation = $this->guestConversation();
        $token = $conversation->ensureGuestToken();

        $this->get('/messages/'.$token)
            ->assertOk()
            ->assertSee('Question de passage')
            ->assertSee('Bonjour, une question.');

        $this->get('/messages/'.Str::random(48))->assertNotFound();
    }

    public function test_a_guest_can_reply_through_their_link(): void
    {
        $conversation = $this->guestConversation();
        $token = $conversation->ensureGuestToken();

        $this->postJson('/messages/'.$token.'/reply', ['body' => 'Merci pour votre réponse !'])
            ->assertOk()
            ->assertJson(['message' => __('store.conversation_reply_sent')]);

        $reply = $conversation->messages()->latest('id')->first();
        $this->assertSame(ConversationMessage::AUTHOR_CUSTOMER, $reply->author_type);
        $this->assertNull($reply->user_id);
        $this->assertSame('Merci pour votre réponse !', $reply->body);
    }

    public function test_a_closed_thread_still_reads_but_no_longer_answers(): void
    {
        $conversation = $this->guestConversation();
        $token = $conversation->ensureGuestToken();
        $conversation->forceFill(['status' => Conversation::STATUS_CLOSED, 'closed_at' => now()->subDays(5)])->save();

        $this->get('/messages/'.$token)
            ->assertOk()
            ->assertSee(__('store.conversation_closed_note'));

        $this->postJson('/messages/'.$token.'/reply', ['body' => 'Encore une chose'])
            ->assertForbidden();
    }

    public function test_the_link_dies_a_month_after_closure(): void
    {
        $conversation = $this->guestConversation();
        $token = $conversation->ensureGuestToken();
        $conversation->forceFill(['status' => Conversation::STATUS_CLOSED, 'closed_at' => now()->subDays(31)])->save();

        $this->get('/messages/'.$token)->assertNotFound();
        $this->postJson('/messages/'.$token.'/reply', ['body' => 'Trop tard'])->assertNotFound();
    }

    public function test_an_admin_reply_reaches_the_guest_with_their_link(): void
    {
        Notification::fake();
        $conversation = $this->guestConversation();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.conversations.reply', $conversation), ['body' => 'Bonjour, voici la réponse.'])
            ->assertOk();

        // The reply itself minted the link an old guest thread never had.
        $this->assertNotNull($conversation->fresh()->guest_token);

        Notification::assertSentOnDemand(
            ConversationReplied::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'jean.passant@example.com',
        );
    }
}
