<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
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

    public function test_a_visit_is_recorded_with_the_session_id(): void
    {
        $this->get('/contact')->assertOk();

        $visit = SiteVisit::query()->where('path', '/contact')->firstOrFail();
        $this->assertNotNull($visit->session_id);
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

    public function test_a_product_page_view_is_recorded_with_its_product_and_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->get('/products/'.$product->slug)->assertOk();

        $this->assertDatabaseHas('site_visits', [
            'product_id' => $product->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_a_category_page_view_is_recorded_with_its_category_but_no_product(): void
    {
        $category = Category::factory()->create();

        $this->get('/categories/'.$category->slug)->assertOk();

        $this->assertDatabaseHas('site_visits', [
            'category_id' => $category->id,
            'product_id' => null,
        ]);
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

    public function test_the_page_carries_a_time_series_and_top_tables(): void
    {
        $human = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        SiteVisit::create(['path' => '/products/cible-a', 'ip_address' => '203.0.113.40', 'user_agent' => $human]);
        SiteVisit::create(['path' => '/products/cible-a', 'ip_address' => '203.0.113.41', 'user_agent' => $human]);
        SiteVisit::create(['path' => '/products/cible-b', 'ip_address' => '203.0.113.42', 'user_agent' => $human, 'referrer' => 'https://www.google.com/search']);
        // Un bot : compté dans la série, jamais dans les palmarès.
        SiteVisit::create(['path' => '/products/cible-c', 'ip_address' => '203.0.113.43', 'user_agent' => 'Googlebot/2.1']);

        $response = $this->actingAsAdmin()->get('/admin/analytics')->assertOk()
            ->assertSee('Visits over time')
            ->assertSee('Top pages')
            ->assertSee('Top referrers')
            ->assertSee('google.com');

        $topPages = $response->viewData('topPages');
        $this->assertSame('/products/cible-a', $topPages[0]['path']);
        $this->assertSame(2, $topPages[0]['count']);
        $this->assertNotContains('/products/cible-c', array_column($topPages, 'path'));

        $series = $response->viewData('series');
        $this->assertSame(3, array_sum(array_column($series, 'humans')));
        $this->assertSame(1, array_sum(array_column($series, 'bots')));
    }

    public function test_the_page_shows_top_products_and_categories(): void
    {
        $human = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        $category = Category::factory()->create(['name' => ['fr' => 'Cibles', 'en' => 'Targets']]);
        $other = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->withHeaders(['User-Agent' => $human])->get('/products/'.$product->slug)->assertOk();
        $this->withHeaders(['User-Agent' => $human])->get('/products/'.$product->slug)->assertOk();
        $this->withHeaders(['User-Agent' => $human])->get('/categories/'.$other->slug)->assertOk();

        $response = $this->actingAsAdmin()->get('/admin/analytics')->assertOk()
            ->assertSee('Top products')
            ->assertSee('Top categories')
            ->assertSee($product->localizedName());

        $topProducts = $response->viewData('topProducts');
        $this->assertSame($product->id, $topProducts[0]['id']);
        $this->assertSame(2, $topProducts[0]['count']);

        $topCategories = collect($response->viewData('topCategories'))->keyBy('id');
        $this->assertSame(2, $topCategories[$category->id]['count']);
        $this->assertSame(1, $topCategories[$other->id]['count']);
    }

    public function test_the_page_shows_a_user_flow_from_entrance_to_order(): void
    {
        $human = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

        // One visitor's session: home, a product, cart, checkout, order.
        $journey = ['/', '/products/tente', '/cart', '/checkout', '/orders/A1B2'];
        $base = now()->subMinutes(30);

        foreach ($journey as $i => $path) {
            $visit = SiteVisit::create(['path' => $path, 'ip_address' => '203.0.113.80', 'user_agent' => $human]);
            $visit->created_at = $base->copy()->addMinutes($i);
            $visit->save();
        }

        // A different visitor who only ever looks at the home page.
        $bounce = SiteVisit::create(['path' => '/', 'ip_address' => '203.0.113.81', 'user_agent' => $human]);
        $bounce->created_at = $base;
        $bounce->save();

        $response = $this->actingAsAdmin()->get('/admin/analytics')->assertOk()
            ->assertSee('User flow')
            ->assertSee('Entrance')
            ->assertSee('Home')
            ->assertSee('Checkout')
            ->assertSee('Left the site');

        $flow = $response->viewData('flow');
        $this->assertSame(2, $flow['total']);

        $entrance = collect($flow['nodes'])->firstWhere('step', 0);
        $this->assertSame('Home', $entrance['label']);
        $this->assertSame(2, $entrance['count']);
    }

    public function test_the_flow_splits_by_real_session_id_even_behind_the_same_ip(): void
    {
        $human = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        $now = now();

        // Two different visitors sharing one IP (e.g. an office NAT) — the
        // session id, not the IP, is what should separate them.
        foreach (['sess-aaa', 'sess-bbb'] as $sessionId) {
            $visit = SiteVisit::create([
                'path' => '/',
                'session_id' => $sessionId,
                'ip_address' => '203.0.113.90',
                'user_agent' => $human,
            ]);
            $visit->created_at = $now;
            $visit->save();
        }

        $flow = $this->actingAsAdmin()->get('/admin/analytics')->viewData('flow');

        $this->assertSame(2, $flow['total']);
    }

    public function test_the_flow_keeps_one_session_together_across_a_long_gap(): void
    {
        $human = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        $base = now()->subHours(2);

        // Same real session id, over an hour apart — a real session id is
        // authoritative, so this must stay one session, not split by the
        // inactivity-gap heuristic used only when there's no session id.
        foreach ([['/' , 0], ['/products/tente', 90]] as [$path, $minutesLater]) {
            $visit = SiteVisit::create([
                'path' => $path,
                'session_id' => 'sess-long',
                'ip_address' => '203.0.113.91',
                'user_agent' => $human,
            ]);
            $visit->created_at = $base->copy()->addMinutes($minutesLater);
            $visit->save();
        }

        $flow = $this->actingAsAdmin()->get('/admin/analytics?range=all')->viewData('flow');

        // One session, not two — proof the gap heuristic didn't split it.
        $this->assertSame(1, $flow['total']);
    }

    public function test_the_trend_leads_and_redundant_donuts_are_gone(): void
    {
        SiteVisit::create([
            'path' => '/',
            'ip_address' => '203.0.113.60',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ]);

        $html = $this->actingAsAdmin()->get('/admin/analytics')->assertOk()
            // Les tuiles portent déjà ces deux répartitions en chiffres.
            ->assertDontSee('Users vs guests')
            ->assertDontSee('Bot vs human')
            ->getContent();

        // La tendance d'abord, la composition ensuite, le journal en dernier.
        $chart = strpos($html, 'Visits over time');
        $tops = strpos($html, 'Top pages');
        $donuts = strpos($html, 'Breakdown');
        $log = strpos($html, 'Visit log');

        $this->assertTrue($chart < $tops && $tops < $donuts && $donuts < $log);
    }

    public function test_the_log_can_hide_bots(): void
    {
        SiteVisit::create(['path' => '/vu-par-un-bot', 'ip_address' => '203.0.113.50', 'user_agent' => 'Googlebot/2.1']);
        SiteVisit::create([
            'path' => '/vu-par-un-humain',
            'ip_address' => '203.0.113.51',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ]);

        $this->actingAsAdmin()->get('/admin/analytics?bots=hide')->assertOk()
            ->assertSee('/vu-par-un-humain')
            ->assertDontSee('/vu-par-un-bot')
            ->assertSee('Bots hidden');

        $this->actingAsAdmin()->get('/admin/analytics')->assertOk()
            ->assertSee('/vu-par-un-bot')
            ->assertSee('Hide bots');
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
