<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Notifications\GuestConversationStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

/**
 * The notification emails wear the shop's own coat: the two-tone wordmark
 * up top, the accent on the button, and a footer that leads somewhere.
 */
class MailThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_emails_render_with_the_shop_theme(): void
    {
        $conversation = Conversation::query()->create([
            'name' => 'Jean Passant',
            'email' => 'jean@example.com',
            'subject' => 'Question',
        ]);
        $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER, null);
        $conversation->ensureGuestToken();

        $html = (string) (new GuestConversationStarted($conversation->fresh()))
            ->toMail(new AnonymousNotifiable)
            ->render();

        $this->assertStringContainsString('>Armo</span>', $html);
        $this->assertStringContainsString('>Outdoor</span>', $html);
        // The action button carries the accent, inlined into the markup.
        $this->assertStringContainsString('#8b7e74', $html);
        $this->assertStringContainsString($conversation->fresh()->guestUrl(), $html);
        $this->assertStringContainsString('Nous contacter', $html);
        // The stock Laravel look is gone.
        $this->assertStringNotContainsString('laravel.com', $html);
        $this->assertStringNotContainsString('#18181b', $html);
    }

    public function test_the_admin_test_email_wears_the_same_theme(): void
    {
        $html = (new \App\Mail\TestMail)->render();

        $this->assertStringContainsString('>Armo</span>', $html);
        $this->assertStringContainsString('>Outdoor</span>', $html);
        $this->assertStringContainsString('#8b7e74', $html);
        $this->assertStringContainsString('La messagerie fonctionne.', $html);
        $this->assertStringContainsString('Nous contacter', $html);
    }
}
