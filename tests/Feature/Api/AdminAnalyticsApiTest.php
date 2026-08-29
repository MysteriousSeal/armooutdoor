<?php

namespace Tests\Feature\Api;

use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'test-admin-api-token']);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-admin-api-token'];
    }

    public function test_the_listing_requires_the_admin_token(): void
    {
        $this->getJson('/api/admin/analytics')->assertUnauthorized();
    }

    public function test_visits_are_listed_newest_first_with_parsed_fields(): void
    {
        $customer = User::factory()->create();

        SiteVisit::create(['path' => '/older', 'ip_address' => '203.0.113.40']);
        SiteVisit::query()->update(['created_at' => now()->subHour()]);
        SiteVisit::create([
            'path' => '/newer',
            'user_id' => $customer->id,
            'ip_address' => '203.0.113.41',
            'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'country' => 'France',
        ]);

        $this->getJson('/api/admin/analytics', $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.path', '/newer')
            ->assertJsonPath('data.0.is_bot', true)
            ->assertJsonPath('data.0.browser', 'Googlebot')
            ->assertJsonPath('data.0.user.id', $customer->id)
            ->assertJsonPath('data.1.path', '/older');
    }
}
