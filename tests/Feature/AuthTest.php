<?php

namespace Tests\Feature;

use App\Models\Product;
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
                'terms' => '1',
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

    public function test_signing_up_from_the_checkout_goes_back_to_the_checkout(): void
    {
        // Checkout sends a guest to log in; they create an account instead.
        $this->get('/checkout')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'email' => 'jean@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'terms' => '1',
        ])->assertRedirect('/checkout');

        $this->assertAuthenticated();
    }

    public function test_signing_up_goes_back_to_the_page_the_visitor_came_from(): void
    {
        $this->from('/cart')->get('/register')->assertOk();

        $this->post('/register', [
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'email' => 'jean@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'terms' => '1',
        ])->assertRedirect('/cart');
    }

    public function test_login_reached_from_the_cart_reads_as_the_last_step_before_paying(): void
    {
        $product = Product::factory()->create(['name' => 'Cagoule Test', 'price_cents' => 690, 'quantity' => 5]);
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        // « Commander » sends a guest from the checkout to the login page.
        $this->get('/checkout')->assertRedirect('/login');

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('Dernière étape avant le paiement', $html);
        $this->assertStringContainsString('Cagoule Test', $html);
        $this->assertStringContainsString('13,80', $html);
        $this->assertMatchesRegularExpression('#<a href="[^"]*/register" class="btn btn-primary btn-block auth-checkout-register">Créer un compte</a>#', $html);
        $this->assertStringContainsString('Déjà client ? Connectez-vous', $html);
        // The new account comes before the returning customer's form.
        $this->assertLessThan(strpos($html, 'name="password"'), strpos($html, 'auth-checkout-register'));
    }

    public function test_a_large_cart_stays_one_compact_row(): void
    {
        foreach (Product::factory()->count(6)->create(['price_cents' => 1000, 'quantity' => 5]) as $product) {
            $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        }

        $this->get('/checkout')->assertRedirect('/login');

        $html = $this->get('/login')->assertOk()->getContent();

        preg_match('#<div class="auth-checkout-thumbs">(.*?)</div>#s', $html, $thumbs);
        $this->assertSame(3, substr_count($thumbs[1], '<img'));
        $this->assertStringContainsString('+3', $thumbs[1]);
        $this->assertStringContainsString('6 articles', $html);
        $this->assertStringContainsString('60,00', $html);
    }

    public function test_a_plain_login_keeps_its_usual_title(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Connexion</h1>', $html);
        $this->assertStringNotContainsString('Dernière étape avant le paiement', $html);
        $this->assertStringNotContainsString('auth-checkout-cart', $html);
    }

    public function test_an_empty_cart_does_not_get_the_checkout_version(): void
    {
        $this->get('/checkout')->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertDontSee('Dernière étape avant le paiement');
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->from('/register')
            ->post('/register', [
                'first_name' => 'Jean',
                'last_name' => 'Test',
                'email' => 'jean@example.com',
                'password' => 'secret-pass',
                'terms' => '1',
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
