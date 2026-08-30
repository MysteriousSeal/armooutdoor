<?php

namespace Tests\Feature;

use App\Models\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CookieBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_the_banner_shows_until_a_choice_is_made(): void
    {
        $this->get('/contact')->assertOk()
            ->assertSee('data-cookie-banner', false)
            // Refuser doit peser autant qu'accepter : deux vrais boutons.
            ->assertSee('data-cookie-choice="essential"', false)
            ->assertSee('data-cookie-choice="all"', false);
    }

    public function test_the_banner_disappears_once_a_choice_is_recorded(): void
    {
        foreach (['all', 'essential'] as $choice) {
            $this->withUnencryptedCookie('cookie_consent', $choice)
                ->get('/contact')->assertOk()
                ->assertDontSee('data-cookie-banner', false);
        }
    }

    public function test_accepting_lets_the_visit_carry_the_session_id(): void
    {
        $this->withUnencryptedCookie('cookie_consent', 'all')->get('/contact')->assertOk();

        $this->assertNotNull(SiteVisit::query()->where('path', '/contact')->firstOrFail()->session_id);
    }

    public function test_declining_or_not_choosing_records_the_visit_without_session_id(): void
    {
        $this->withUnencryptedCookie('cookie_consent', 'essential')->get('/contact')->assertOk();
        $this->flushSession();
        $this->get('/cgv')->assertOk();

        $this->assertNull(SiteVisit::query()->where('path', '/contact')->firstOrFail()->session_id);
        $this->assertNull(SiteVisit::query()->where('path', '/cgv')->firstOrFail()->session_id);
    }

    public function test_the_footer_offers_to_reopen_the_choice(): void
    {
        $this->withUnencryptedCookie('cookie_consent', 'all')
            ->get('/contact')->assertOk()
            ->assertSee('data-cookie-reopen', false);
    }
}
