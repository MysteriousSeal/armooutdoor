<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_registration_page_is_available(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Créer un compte');
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Se connecter');
    }

    public function test_a_visitor_can_create_an_account(): void
    {
        $this->from('/register')
            ->post('/register', [
                'first_name' => 'Jean',
                'last_name' => 'Test',
                'email' => 'jean@example.com',
                'password' => 'secret-pass',
                'password_confirmation' => 'secret-pass',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('status');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'email' => 'jean@example.com',
        ]);
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->from('/register')
            ->post('/register', [
                'first_name' => 'Jean',
                'last_name' => 'Test',
                'email' => 'jean@example.com',
                'password' => 'secret-pass',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create([
            'email' => 'jean@example.com',
            'password' => 'secret-pass',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'jean@example.com',
                'password' => 'secret-pass',
            ])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->from('/')
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'jean@example.com',
            'password' => 'secret-pass',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'jean@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_away_from_auth_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/');

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect('/');
    }
}
