<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\ConversationReplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Emailing a customer that their answer is waiting — and, more importantly,
 * never emailing anyone we shouldn't.
 */
class ConversationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function conversationFor(?User $user): Conversation
    {
        $conversation = Conversation::query()->create([
            'user_id' => $user?->id,
            'name' => $user?->name ?? 'Jean Martin',
            'email' => $user?->email ?? 'jean@example.com',
            'subject' => 'Question sur ma commande',
        ]);

        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER, $user);

        return $conversation;
    }

    public function test_an_admin_reply_notifies_the_customer(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);

        $this->actingAs($admin)->post(route('admin.conversations.reply', $conversation), [
            'body' => 'Votre commande part demain.',
        ]);

        Notification::assertSentTo($customer, ConversationReplied::class);
    }

    public function test_a_customer_reply_notifies_nobody(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);

        $this->actingAs($customer)->post('/account/messages/'.$conversation->id.'/reply', [
            'body' => 'Merci',
        ]);

        Notification::assertNothingSent();
    }

    public function test_a_guest_thread_never_produces_a_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $conversation = $this->conversationFor(null);

        // Replying is refused outright for a guest thread…
        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $conversation), ['body' => 'Bonjour'])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_the_notification_declines_to_send_on_a_guest_thread(): void
    {
        // …and even called directly, it refuses to pick a delivery channel.
        $conversation = $this->conversationFor(null);
        $notification = new ConversationReplied($conversation);

        $this->assertSame([], $notification->via(new User));
    }

    public function test_the_notification_uses_mail_for_a_customer_thread(): void
    {
        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);

        $this->assertSame(['mail'], (new ConversationReplied($conversation))->via($customer));
    }

    public function test_the_email_links_the_thread_without_quoting_the_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);
        $conversation->postMessage('Un secret commercial.', ConversationMessage::AUTHOR_ADMIN, $admin);

        $mail = (new ConversationReplied($conversation))->toMail($customer);
        $rendered = (string) $mail->render();

        $this->assertStringContainsString(
            route('account.conversations.show', ['conversation' => $conversation]),
            $rendered,
        );
        $this->assertStringNotContainsString('Un secret commercial.', $rendered);
    }

    public function test_the_email_does_not_name_the_admin_who_replied(): void
    {
        $admin = User::factory()->admin()->create([
            'first_name' => 'Julie',
            'last_name' => 'Simmons',
            'email' => 'julie@armooutdoor.test',
        ]);
        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);
        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_ADMIN, $admin);

        $rendered = (string) (new ConversationReplied($conversation))->toMail($customer)->render();

        $this->assertStringNotContainsString('Julie', $rendered);
        $this->assertStringNotContainsString('Simmons', $rendered);
        $this->assertStringNotContainsString('julie@armooutdoor.test', $rendered);
        $this->assertStringContainsString(config('app.name'), $rendered);
    }

    public function test_the_email_is_addressed_to_the_customer_account(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);

        $this->actingAs($admin)->post(route('admin.conversations.reply', $conversation), [
            'body' => 'Bonjour',
        ]);

        Notification::assertSentTo(
            $customer,
            ConversationReplied::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routeNotificationFor('mail') === $customer->email,
        );
    }

    public function test_a_mail_failure_does_not_break_the_reply(): void
    {
        Log::spy();

        // A transport that always throws, standing in for an SMTP outage.
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('smtp down'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);

        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $conversation), ['body' => 'Votre commande part demain.'])
            ->assertRedirect();

        // The reply itself survived, which is the part that must not be lost.
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Votre commande part demain.',
            'author_type' => ConversationMessage::AUTHOR_ADMIN,
        ]);

        Log::shouldHaveReceived('error')->once();
    }
}
