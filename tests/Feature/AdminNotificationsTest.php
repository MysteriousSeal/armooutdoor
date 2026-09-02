<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\AdminConversationReceived;
use App\Notifications\AdminIdentityDocumentSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The notices the shop sends itself: a conversation turning unread, a
 * proof of age arriving. Each waits on a human, and none of them should
 * depend on somebody thinking to log in. Everything goes through HTTP —
 * the mails are deferred to the request's termination, which a direct
 * model call in a test would never reach.
 */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.admin_notification_email' => 'shop@armooutdoor.test']);
        Notification::fake();
    }

    private function conversationFor(User $user): Conversation
    {
        return Conversation::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Question',
        ]);
    }

    public function test_a_message_turning_a_thread_unread_emails_the_shop_once(): void
    {
        $customer = User::factory()->create();
        $conversation = $this->conversationFor($customer);

        // Each real request gets a fresh application; the test's requests
        // share one, whose terminating callbacks would otherwise re-fire
        // the first send on every later request.
        $reply = function (string $body) use ($customer, $conversation): void {
            $this->actingAs($customer)
                ->post(route('account.conversations.reply', $conversation), ['body' => $body]);
            (fn () => $this->terminatingCallbacks = [])->call($this->app);
        };

        $reply('Bonjour');
        // Still unread: the follow-up must not email a second time.
        $reply('Vous êtes là ?');

        Notification::assertSentTimes(AdminConversationReceived::class, 1);

        // Read, then a new message: that transition earns one more email.
        $conversation->refresh()->markReadForAdmin();
        $conversation->save();
        $reply('Autre question');

        Notification::assertSentTimes(AdminConversationReceived::class, 2);
    }

    public function test_an_admin_reply_never_emails_the_shop(): void
    {
        $conversation = $this->conversationFor(User::factory()->create());

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.conversations.reply', $conversation), ['body' => 'Réponse.']);

        Notification::assertNotSentTo(new AnonymousNotifiable, AdminConversationReceived::class);
    }

    public function test_the_contact_form_reaches_the_shop_inbox(): void
    {
        $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une cible',
            'message' => 'Bonjour, est-elle auto-adhésive ?',
        ]);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            AdminConversationReceived::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'shop@armooutdoor.test',
        );
    }

    public function test_a_submitted_identity_document_emails_the_shop(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post('/account/documents', [
                'kind' => 'passport',
                'document' => UploadedFile::fake()->image('passeport.jpg'),
            ]);

        Notification::assertSentTimes(AdminIdentityDocumentSubmitted::class, 1);
    }

    public function test_a_new_customer_account_emails_the_shop(): void
    {
        $this->post('/register', [
            'first_name' => 'Jean',
            'last_name' => 'Martin',
            'email' => 'jean.martin@example.com',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ]);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            \App\Notifications\AdminCustomerRegistered::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'shop@armooutdoor.test',
        );
    }

    public function test_an_empty_address_means_nobody_is_emailed(): void
    {
        config(['shop.admin_notification_email' => '']);

        $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour.',
        ]);

        Notification::assertNotSentTo(new AnonymousNotifiable, AdminConversationReceived::class);
    }
}
