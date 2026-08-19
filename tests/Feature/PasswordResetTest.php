<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_request_a_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jean@example.com']);

        $this->post('/forgot-password', ['email' => 'jean@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_requesting_a_reset_link_for_an_unknown_email_does_not_error(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');
    }

    public function test_a_user_can_reset_their_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'jean@example.com']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'jean@example.com',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('brand-new-secret', $user->fresh()->password));

        $this->post('/login', [
            'email' => 'jean@example.com',
            'password' => 'brand-new-secret',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'jean@example.com']);

        $this->from('/reset-password/bad-token')
            ->post('/reset-password', [
                'token' => 'bad-token',
                'email' => 'jean@example.com',
                'password' => 'brand-new-secret',
                'password_confirmation' => 'brand-new-secret',
            ])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_requires_a_confirmed_password(): void
    {
        $user = User::factory()->create(['email' => 'jean@example.com']);
        $token = Password::createToken($user);

        $this->from('/reset-password/'.$token)
            ->post('/reset-password', [
                'token' => $token,
                'email' => 'jean@example.com',
                'password' => 'brand-new-secret',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_authenticated_users_cannot_access_the_forgot_password_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/forgot-password')
            ->assertRedirect('/');
    }
}
