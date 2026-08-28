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

    public function test_an_unknown_email_gets_the_same_answer_as_a_known_one(): void
    {
        Notification::fake();

        // Nothing is sent, but the page must not say so: an error here would
        // let anyone probe which addresses hold an account.
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('store.password_reset_sent'));

        Notification::assertNothingSent();
    }

    public function test_asking_twice_within_the_cooldown_explains_the_wait(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasErrors(['email' => __('store.password_reset_throttled')]);
    }

    public function test_the_cooldown_cannot_tell_real_accounts_from_unknown_ones(): void
    {
        Notification::fake();

        // An address with no account cools down exactly like one with an
        // account — otherwise the cooldown error itself would answer "does
        // this email have an account here".
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHasNoErrors();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors(['email' => __('store.password_reset_throttled')]);
    }

    public function test_the_form_can_ask_in_json_and_gets_the_same_answers(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        // Known and unknown addresses answer alike here too.
        $this->postJson('/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJson(['message' => __('store.password_reset_sent')]);
        $this->postJson('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['message' => __('store.password_reset_sent')]);

        // The cooldown comes back as a field error the page shows inline.
        $this->postJson('/forgot-password', ['email' => $user->email])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('store.password_reset_throttled'));
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

        // Token and passwords only: the form neither shows nor sends the
        // email — the server works out whose token it is.
        $this->post('/reset-password', [
            'token' => $token,
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
                'password' => 'brand-new-secret',
                'password_confirmation' => 'brand-new-secret',
            ])
            ->assertSessionHasErrors('token');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_requires_a_confirmed_password(): void
    {
        $user = User::factory()->create(['email' => 'jean@example.com']);
        $token = Password::createToken($user);

        $this->from('/reset-password/'.$token)
            ->post('/reset-password', [
                'token' => $token,
                'password' => 'brand-new-secret',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_the_reset_link_and_page_never_carry_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jean@example.com']);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class, function ($notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return ! str_contains($url, 'email') && ! str_contains($url, urlencode($user->email));
        });

        // The form page shows passwords only — no address for a shoulder to read.
        $token = Password::createToken($user);
        $this->get('/reset-password/'.$token)
            ->assertOk()
            ->assertDontSee('jean@example.com');
    }

    public function test_authenticated_users_cannot_access_the_forgot_password_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/forgot-password')
            ->assertRedirect('/');
    }
}
