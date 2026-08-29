<?php

namespace Tests\Feature;

use App\Models\SiteVisit;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        // The middleware resolves geo per IP; local test IPs short-circuit
        // before any HTTP call, but fake the client so a change there can
        // never make the suite hit ip-api.com.
        Http::fake();
    }

    private function actingAsAdmin(): self
    {
        $admin = User::query()->where('email', 'admin@armooutdoor.test')->firstOrFail();

        return $this->actingAs($admin);
    }

    // ------------------------------------------------------------ recording

    public function test_a_public_page_view_is_recorded(): void
    {
        $this->get('/contact')->assertOk();

        $this->assertDatabaseHas('site_visits', ['path' => '/contact', 'country' => 'Local']);
    }

    public function test_admin_pages_and_non_get_requests_are_not_recorded(): void
    {
        $this->get('/admin');
        $this->post('/contact', []);
        $this->getJson('/contact');

        $this->assertDatabaseCount('site_visits', 0);
    }

    public function test_a_logged_in_customer_is_attached_to_the_visit(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/contact')->assertOk();

        $this->assertDatabaseHas('site_visits', ['path' => '/contact', 'user_id' => $customer->id]);
    }

    public function test_human_visits_from_public_ips_trigger_the_geo_lookup(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'])
            ->get('/contact')
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'ip-api.com'));
        $this->assertDatabaseHas('site_visits', ['ip_address' => '203.0.113.9']);
    }

    public function test_bot_visits_skip_the_geo_lookup(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['User-Agent' => 'curl/8.6.0'])
            ->get('/contact')
            ->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseHas('site_visits', ['ip_address' => '203.0.113.9', 'country' => null]);
    }

    // ----------------------------------------------------------------- page

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get('/admin/analytics')->assertRedirect('/admin');
    }

    public function test_customers_cannot_open_the_analytics_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/analytics')
            ->assertRedirect('/admin');
    }

    public function test_an_admin_sees_the_analytics_page_with_recorded_visits(): void
    {
        SiteVisit::create([
            'path' => '/some-product',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'country' => 'France',
            'city' => 'Lyon',
        ]);

        $this->actingAsAdmin()
            ->get('/admin/analytics')
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('/some-product')
            ->assertSee('Lyon, France')
            ->assertSee('Chrome');
    }

    public function test_the_default_range_only_shows_recent_visits(): void
    {
        SiteVisit::create(['path' => '/old-page', 'ip_address' => '203.0.113.10']);
        SiteVisit::query()->update(['created_at' => now()->subDays(10)]);
        SiteVisit::create(['path' => '/fresh-page', 'ip_address' => '203.0.113.11']);

        $this->actingAsAdmin()
            ->get('/admin/analytics')
            ->assertOk()
            ->assertSee('/fresh-page')
            ->assertDontSee('/old-page');

        $this->actingAsAdmin()
            ->get('/admin/analytics?range=all')
            ->assertOk()
            ->assertSee('/old-page');
    }

    public function test_bots_are_counted_apart_from_guests(): void
    {
        SiteVisit::create([
            'path' => '/',
            'ip_address' => '203.0.113.20',
            'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);
        SiteVisit::create([
            'path' => '/',
            'ip_address' => '203.0.113.21',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ]);

        $response = $this->actingAsAdmin()->get('/admin/analytics')->assertOk();

        $this->assertSame(1, $response->viewData('rangeVisitors')['bots']);
        $this->assertSame(1, $response->viewData('rangeVisitors')['guests']);
    }

    public function test_the_active_now_endpoint_returns_live_counts(): void
    {
        SiteVisit::create(['path' => '/', 'ip_address' => '203.0.113.30']);

        $this->actingAsAdmin()
            ->getJson('/admin/analytics/active-now')
            ->assertOk()
            ->assertJson(['total' => 1, 'users' => 0, 'guests' => 1, 'bots' => 0]);
    }
}
