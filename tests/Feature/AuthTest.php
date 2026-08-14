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
        $this->get('/fr/register')
            ->assertOk()
            ->assertSee('Créer un compte');
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/fr/login')
            ->assertOk()
            ->assertSee('Se connecter');
    }

    public function test_a_visitor_can_create_an_account(): void
    {
        $this->from('/fr/register')
            ->post('/fr/register', [
                'name' => 'Colas',
                'email' => 'colas@example.com',
                'password' => 'secret-pass',
                'password_confirmation' => 'secret-pass',
            ])
            ->assertRedirect('/fr')
            ->assertSessionHas('status');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Colas',
            'email' => 'colas@example.com',
        ]);
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->from('/fr/register')
            ->post('/fr/register', [
                'name' => 'Colas',
                'email' => 'colas@example.com',
                'password' => 'secret-pass',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect('/fr/register')
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create([
            'email' => 'colas@example.com',
            'password' => 'secret-pass',
        ]);

        $this->from('/fr/login')
            ->post('/fr/login', [
                'email' => 'colas@example.com',
                'password' => 'secret-pass',
            ])
            ->assertRedirect('/fr');

        $this->assertAuthenticatedAs($user);

        $this->from('/fr')
            ->post('/fr/logout')
            ->assertRedirect('/fr');

        $this->assertGuest();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'colas@example.com',
            'password' => 'secret-pass',
        ]);

        $this->from('/fr/login')
            ->post('/fr/login', [
                'email' => 'colas@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/fr/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_away_from_auth_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/fr/login')
            ->assertRedirect('/fr');

        $this->actingAs($user)
            ->get('/fr/register')
            ->assertRedirect('/fr');
    }
}
