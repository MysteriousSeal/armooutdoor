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
    protected function setUp(): void
    {
        parent::setUp();

        // The Ads knobs live in .env; the older expectations here assume
        // them absent, so each test opts in explicitly.
        config(['services.google_ads.id' => null, 'services.google_ads.conversion_label' => null]);
    }

    use RefreshDatabase;

    private function csp(): string
    {
        return (new SecurityHeaders)
            ->handle(Request::create('/'), fn () => new Response)
            ->headers->get('Content-Security-Policy');
    }

    public function test_nothing_is_loaded_where_no_key_is_configured(): void
    {
        config(['services.posthog.key' => null, 'services.google_analytics.id' => null]);

        $this->get('/')->assertOk()->assertDontSee('analytics.js', false);
    }

    public function test_the_policy_stays_shut_where_no_key_is_configured(): void
    {
        config(['services.posthog.key' => null, 'services.google_analytics.id' => null]);

        $this->assertStringNotContainsString('posthog', $this->csp());
        $this->assertStringNotContainsString('connect-src', $this->csp());
    }

    public function test_the_policy_opens_for_that_one_host_and_no_other(): void
    {
        // Google off: this test is about PostHog's host and nothing else.
        config([
            'services.posthog.key' => 'phc_test',
            'services.posthog.host' => 'https://eu.i.posthog.com',
            'services.google_analytics.id' => null,
        ]);

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
        // PostHog has no consent mode of its own, so the answer decides
        // whether it is fetched at all.
        $this->assertStringContainsString('!config.key || !accepted()', $js);
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

    public function test_google_is_not_loaded_without_a_measurement_id(): void
    {
        config(['services.google_analytics.id' => null, 'services.posthog.key' => null]);

        $this->assertStringNotContainsString('googletagmanager', $this->csp());
        $this->get('/')->assertOk()->assertDontSee('analytics.js', false);
    }

    public function test_the_policy_opens_for_google_when_a_measurement_id_is_set(): void
    {
        config(['services.google_analytics.id' => 'G-TEST', 'services.posthog.key' => null]);

        $csp = $this->csp();

        $this->assertStringContainsString('https://www.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://www.google-analytics.com', $csp);
        // PostHog is off here, so its host has no business in the header.
        $this->assertStringNotContainsString('posthog', $csp);
    }

    public function test_both_tools_share_one_policy_without_a_wildcard_host(): void
    {
        config(['services.google_analytics.id' => 'G-TEST', 'services.posthog.key' => 'phc_test']);

        $csp = $this->csp();

        $this->assertStringContainsString('eu.i.posthog.com', $csp);
        $this->assertStringContainsString('googletagmanager.com', $csp);
        // Google's collectors are wildcarded by subdomain, never by scheme.
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString(' *', $csp);
    }

    public function test_the_measurement_id_reaches_the_page_as_data(): void
    {
        config(['services.google_analytics.id' => 'G-TEST']);

        $this->get('/')->assertOk()->assertSee('"ga":"G-TEST"', false);
    }

    public function test_google_runs_without_storage_until_the_answer_is_given(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        // Consent Mode v2: the tag runs either way, and what changes is what
        // it may keep on the device. Its default has to be queued before the
        // tag is fetched, or the tag starts having assumed it could store.
        $this->assertStringContainsString("window.gtag('consent', 'default', consent(granted));", $js);
        $this->assertLessThan(
            strpos($js, "googletagmanager.com/gtag/js"),
            strpos($js, "window.gtag('consent', 'default'"),
            'The consent default has to be queued before the tag is requested.'
        );

        // Denied is what an unanswered banner and a refusal both mean.
        foreach (['analytics_storage', 'ad_storage', 'ad_user_data'] as $signal) {
            $this->assertStringContainsString($signal.": granted ? 'granted' : 'denied',", $js);
        }

        // Without storage the ad click id is stripped from the request.
        $this->assertStringContainsString("window.gtag('set', 'ads_data_redaction', !granted);", $js);
    }

    public function test_advertising_personalisation_is_refused_on_either_answer(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        // The one signal the banner cannot grant: the shop measures its own
        // advertising and does not build audiences out of its visitors.
        $this->assertStringContainsString("ad_personalization: 'denied',", $js);
        $this->assertStringNotContainsString("ad_personalization: granted", $js);
        $this->assertStringContainsString('allow_ad_personalization_signals: false', $js);
        $this->assertStringContainsString('allow_google_signals: false', $js);

        // GA4 truncates the address on its own and ignores the old flag, so
        // the flag stated something the shop was not in fact doing.
        $this->assertStringNotContainsString('anonymize_ip', $js);
    }

    public function test_accepting_updates_the_signals_rather_than_reloading(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        // The tag is already running by then; only the update can widen what
        // it may store, and the redaction is lifted with it.
        $this->assertStringContainsString("window.gtag('consent', 'update', consent(true));", $js);
        $this->assertStringContainsString("window.gtag('set', 'ads_data_redaction', false);", $js);
        $this->assertStringContainsString('data-cookie-choice="all"', $js);
    }

    public function test_the_privacy_policy_admits_the_tag_still_runs_on_a_refusal(): void
    {
        // The page used to promise that a refusal loaded nothing at all.
        // Consent mode made that false, and a policy that overstates what a
        // refusal buys is worse than the tag it describes.
        $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('mode sans consentement', false)
            ->assertSee('ne dépose aucun cookie', false)
            ->assertDontSee("aucun de ces scripts n'est chargé", false);
    }

    public function test_an_event_reaches_whichever_tools_are_running(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        $this->assertStringContainsString('function capture(name, properties, google)', $js);
        $this->assertStringContainsString("window.gtag('event', google.name", $js);
    }

    public function test_an_event_is_held_rather_than_dropped_while_posthog_loads(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        // The library is a network request behind the page, so a purchase
        // captured on a freshly loaded confirmation page used to reach
        // Google and nobody else. It is queued now, and replayed on load.
        $this->assertStringContainsString('pending.push([name, properties || {}]);', $js);
        $this->assertStringContainsString('window.posthog.capture(queued[0], queued[1]);', $js);

        // Queued only where PostHog is configured, so a shop without it does
        // not grow a list nothing will ever read.
        $this->assertStringContainsString('} else if (config.key) {', $js);
    }

    public function test_the_page_event_has_one_call_site(): void
    {
        $js = file_get_contents(public_path('js/analytics.js'));

        // It used to fire on load and again on a click, which on a
        // confirmation page is a sale counted twice.
        $this->assertSame(1, substr_count($js, "window.gtag('event', 'conversion'"));
        $this->assertSame(1, substr_count($js, 'capture(config.event.name'));
        $this->assertStringNotContainsString('window.setTimeout(fire, 400)', $js);
    }

    public function test_the_banner_holds_the_first_layer_and_the_policy_the_rest(): void
    {
        // The CNIL's first layer: purposes, actors, the right to change
        // one's mind. The where and the what live in the second layer.
        $text = __('store.cookie_banner_text');

        $this->assertStringContainsString('audience', $text);
        $this->assertStringContainsString('annonces', $text);
        $this->assertStringContainsString('Google', $text);
        $this->assertStringContainsString('PostHog', $text);
        $this->assertStringContainsString('changer d\'avis', $text);

        $this->get('/confidentialite')->assertOk()
            ->assertSee('Google Analytics')
            ->assertSee('États-Unis');
    }

    public function test_the_privacy_policy_states_the_transfer_basis(): void
    {
        $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('Google Analytics', false)
            ->assertSee('adéquation', false);
    }

    public function test_the_ads_tag_and_conversion_ride_the_same_consent_gate(): void
    {
        config([
            'services.google_ads.id' => 'AW-18433527411',
            'services.google_ads.conversion_label' => 'TESTLABEL123',
        ]);

        // The config block carries the AW id for the loader.
        $this->get('/')->assertOk()->assertSee('"aw":"AW-18433527411"', false);

        // And the confirmation page hands the conversion payload, dedupe
        // key included.
        $user = \App\Models\User::factory()->create();
        $order = \App\Models\Order::query()->create([
            'number' => \App\Models\Order::generateNumber(),
            'user_id' => $user->id,
            'status' => 'paid',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 1000, 'payment_method' => 'card',
        ]);

        $this->actingAs($user)
            ->withSession(['order_placed' => true])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('"send_to":"AW-18433527411/TESTLABEL123"', false)
            ->assertSee($order->number);

        // The CSP opens the Ads doors only when the id is configured.
        $this->get('/')->assertHeader('Content-Security-Policy');
        $this->assertStringContainsString(
            'googleadservices.com',
            $this->get('/')->headers->get('Content-Security-Policy'),
        );
    }
}
