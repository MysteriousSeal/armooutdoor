<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The auth pages' contract: one real h1 apiece, the password rule said
 * before it fails, the acceptance line, and the toggle script on every
 * page holding a password field.
 */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_auth_page_has_exactly_one_h1(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), $path);
        }
    }

    public function test_register_says_the_password_rule_and_the_acceptance(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('minlength="8"', false)
            ->assertSee('8 caractères minimum')
            ->assertSee('conditions générales de vente')
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.privacy'))
            // A choice made, not a line skimmed: unchecked box, novalidate
            // form, the script's warning slot ready.
            ->assertSee('name="terms"', false)
            ->assertDontSee('name="terms" value="1" data-terms checked', false)
            ->assertSee('novalidate', false)
            ->assertSee('data-terms-warning', false)
            ->assertSee('js/register-validate.js');
    }

    public function test_registration_refuses_without_the_terms_box(): void
    {
        $this->post('/register', [
            'first_name' => 'Jean',
            'last_name' => 'Martin',
            'email' => 'jean@example.com',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    public function test_the_toggle_script_rides_the_pages_with_password_fields(): void
    {
        $this->get('/login')->assertOk()->assertSee('js/password-toggle.js');
        $this->get('/register')->assertOk()->assertSee('js/password-toggle.js');
        // No password field, no script.
        $this->get('/forgot-password')->assertOk()->assertDontSee('js/password-toggle.js');
    }

    public function test_both_pages_carry_the_brand_panel_beside_the_form(): void
    {
        foreach (['/login', '/register'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('auth-brand', false)
                ->assertSee('tenue par des passionnés de tir sportif')
                ->assertSee(route('about'));
        }
    }

    public function test_the_email_inputs_refuse_mobile_autocorrections(): void
    {
        $this->get('/login')->assertOk()->assertSee('autocapitalize="none"', false);
        $this->get('/register')->assertOk()->assertSee('autocapitalize="none"', false);
    }
}
