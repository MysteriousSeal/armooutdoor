<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Audience measurement by a third party, and the two things that have to be
 * true before it loads: a key, and the visitor's word.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function csp(): string
    {
        return (new SecurityHeaders)
            ->handle(Request::create('/'), fn () => new Response)
            ->headers->get('Content-Security-Policy');
    }

    public function test_nothing_is_loaded_where_no_key_is_configured(): void
    {
        config(['services.posthog.key' => null]);

        $this->get('/')->assertOk()->assertDontSee('analytics.js', false);
    }

    public function test_the_policy_stays_shut_where_no_key_is_configured(): void
    {
        config(['services.posthog.key' => null]);

        $this->assertStringNotContainsString('posthog', $this->csp());
        $this->assertStringNotContainsString('connect-src', $this->csp());
    }

    public function test_the_policy_opens_for_that_one_host_and_no_other(): void
    {
        config(['services.posthog.key' => 'phc_test', 'services.posthog.host' => 'https://eu.i.posthog.com']);

        $csp = $this->csp();

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' https://eu.i.posthog.com", $csp);
        $this->assertStringContainsString("connect-src 'self' https://eu.i.posthog.com", $csp);
        // The rest of the policy is untouched: no wildcard sneaks in with it.
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('*', $csp);
    }

    public function test_the_key_and_host_reach_the_page_as_data(): void
    {
        config(['services.posthog.key' => 'phc_test']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<script type="application/json" id="analytics-config">', false)
            ->assertSee('"key":"phc_test"', false)
            ->assertSee('"host":"https://eu.i.posthog.com"', false);
    }

    public function test_the_loader_waits_for_a_positive_answer(): void
    {
        // The gate is in the script rather than the markup, since the cookie
        // is written client-side. Refusal and silence both fail this test.
        $js = file_get_contents(public_path('js/analytics.js'));

        $this->assertStringContainsString('cookie_consent=all', $js);
        $this->assertStringContainsString('if (window.posthog || !accepted())', $js);
    }

    public function test_it_never_records_what_a_customer_types(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        $this->assertStringContainsString('autocapture: false', $js);
        $this->assertStringContainsString('disable_session_recording: true', $js);
        $this->assertStringContainsString('mask_all_text: true', $js);
    }

    public function test_the_banner_no_longer_promises_there_is_no_third_party(): void
    {
        // It used to say « sans aucun tiers », which PostHog makes untrue.
        $text = __('store.cookie_banner_text');

        $this->assertStringNotContainsString('sans aucun tiers', $text);
        $this->assertStringContainsString('PostHog', $text);
    }

    public function test_the_privacy_policy_names_the_processor(): void
    {
        $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('PostHog', false)
            ->assertSee('Union européenne', false);
    }
}
