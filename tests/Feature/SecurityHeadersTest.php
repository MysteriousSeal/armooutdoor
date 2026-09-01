<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_carry_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_strict_transport_security_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $response = $this->get('https://localhost/');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_private_pages_tell_crawlers_noindex_even_on_the_guest_redirect(): void
    {
        // The gated pages answer a guest with a 302 — the header must ride
        // that redirect, a crawler never seeing the page behind it.
        $this->get('/account')->assertRedirect()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/checkout')->assertRedirect()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/orders')->assertRedirect()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // The cart is public but personal: rendered, and still noindexed.
        $this->get('/cart')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_doors_and_the_shop_itself_stay_indexable(): void
    {
        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/login')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/register')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }
}
