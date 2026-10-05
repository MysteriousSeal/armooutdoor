<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Welcome;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);
    }

    private function googleReturns(array $raw): void
    {
        $raw += [
            'sub' => '1234567890',
            'email' => 'marie@gmail.com',
            'email_verified' => true,
            'name' => 'Marie Durand',
            'given_name' => 'Marie',
            'family_name' => 'Durand',
        ];

        $user = (new SocialiteUser)->setRaw($raw)->map([
            'id' => $raw['sub'],
            'name' => $raw['name'],
            'email' => $raw['email'],
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    public function test_the_button_shows_only_when_google_is_configured(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continuer avec Google');
        $this->get('/register')->assertOk()->assertSee('Continuer avec Google');

        config(['services.google.client_id' => null]);

        $this->get('/login')->assertOk()->assertDontSee('Continuer avec Google');
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    public function test_the_button_sends_the_visitor_to_google(): void
    {
        $this->get('/auth/google')
            ->assertRedirectContains('accounts.google.com');
    }

    public function test_a_new_visitor_gets_an_account(): void
    {
        Notification::fake();
        $this->googleReturns([]);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect('/')
            ->assertSessionHas('status', __('store.registered'));

        $user = User::query()->where('email', 'marie@gmail.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('1234567890', $user->google_id);
        $this->assertSame('Marie', $user->first_name);
        $this->assertSame('Durand', $user->last_name);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentTo($user, Welcome::class);
    }

    public function test_a_returning_customer_is_found_by_their_google_id(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->forceFill(['google_id' => '1234567890'])->save();

        // The address on the Google account changed; the person did not.
        $this->googleReturns(['email' => 'new@example.com']);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertSessionHas('status', __('store.logged_in'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
        Notification::assertNothingSent();
    }

    public function test_a_gmail_address_links_the_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'Marie@Gmail.com', 'external' => true]);

        $this->googleReturns([]);

        $this->get('/auth/google/callback?code=abc&state=xyz')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('1234567890', $user->google_id);
        $this->assertFalse($user->external);
    }

    public function test_a_workspace_address_links_the_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'marie@armurerie.fr']);

        $this->googleReturns(['email' => 'marie@armurerie.fr', 'hd' => 'armurerie.fr']);

        $this->get('/auth/google/callback?code=abc&state=xyz');

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_address_google_does_not_own_does_not_take_over_an_account(): void
    {
        $user = User::factory()->create(['email' => 'marie@orange.fr']);

        $this->googleReturns(['email' => 'marie@orange.fr']);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['google' => __('store.google_use_password')]);

        $this->assertGuest();
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_an_unverified_address_is_refused(): void
    {
        $this->googleReturns(['email_verified' => false]);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['google' => __('store.google_unverified')]);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_an_admin_account_keeps_its_password(): void
    {
        User::factory()->create(['email' => 'marie@gmail.com', 'is_admin' => true]);

        $this->googleReturns([]);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertSessionHasErrors(['google' => __('store.google_admin')]);

        $this->assertGuest();
    }

    public function test_a_banned_customer_is_refused(): void
    {
        User::factory()->create(['email' => 'marie@gmail.com', 'banned_at' => now()]);

        $this->googleReturns([]);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertSessionHasErrors(['google' => __('store.account_banned')]);

        $this->assertGuest();
    }

    public function test_cancelling_at_google_comes_back_to_the_login_page(): void
    {
        $this->get('/auth/google/callback?error=access_denied')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['google' => __('store.google_cancelled')]);
    }

    public function test_a_stale_round_trip_is_refused(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['google' => __('store.google_failed')]);

        $this->assertGuest();
    }

    public function test_the_error_is_shown_on_the_login_page(): void
    {
        $this->googleReturns(['email_verified' => false]);

        $this->followingRedirects()
            ->get('/auth/google/callback?code=abc&state=xyz')
            ->assertSee(__('store.google_unverified'));
    }
}
