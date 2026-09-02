<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Welcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_receives_a_welcome_in_french(): void
    {
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'Jean',
            'last_name' => 'Martin',
            'email' => 'jean.martin@example.com',
            'password' => 'motdepasse-solide',
            'terms' => '1',
            'password_confirmation' => 'motdepasse-solide',
        ]);

        $user = User::query()->where('email', 'jean.martin@example.com')->firstOrFail();

        Notification::assertSentTo($user, Welcome::class, function (Welcome $notification) use ($user): bool {
            $html = (string) $notification->toMail($user)->render();

            return str_contains($html, 'Bienvenue, Jean')
                && str_contains($html, 'garde vos adresses')
                && str_contains($html, localized_route('contact.show'))
                && str_contains($html, 'Découvrir la boutique');
        });
    }

    public function test_nobody_else_is_welcomed(): void
    {
        Notification::fake();

        // A failed registration sends nothing.
        $this->post('/register', ['email' => 'broken']);

        Notification::assertNothingSent();
    }
}
