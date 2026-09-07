<?php

namespace Tests\Feature;

use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The NaturaBuy block: figures typed in the back office, shown on the home
 * page, linked in a way that sends no signal to a competing marketplace.
 */
class HomeMarketplaceBlockTest extends TestCase
{
    use RefreshDatabase;

    private function configure(array $overrides = []): void
    {
        MarketplaceSetting::current()->update(array_merge([
            'naturabuy_url' => 'https://www.naturabuy.fr/boutique-armo',
            'naturabuy_rating_tenths' => 49,
            'naturabuy_reviews' => 127,
            'naturabuy_on_home' => true,
        ], $overrides));
    }

    public function test_the_block_shows_the_rating_and_the_count(): void
    {
        $this->configure();

        $this->get('/')->assertOk()
            // The line ends on the mark, which stands in for the word.
            ->assertSee('Retrouvez-nous sur')
            ->assertSee('aria-label="NaturaBuy"', false)
            // Not a heading: the home page's outline stays the shop's own.
            ->assertDontSee('<h2 class="home-market-title"', false)
            ->assertSee('4,9')
            ->assertSee('127 avis');
    }

    public function test_the_mark_is_drawn_inline_and_named(): void
    {
        $this->configure();

        $this->get('/')->assertOk()
            // Inline rather than an <img>: only then can the wordmark be
            // given a light ink on the dark theme.
            ->assertSee('class="home-market-logo"', false)
            ->assertSee('aria-label="NaturaBuy"', false)
            ->assertSee('home-market-logo-word', false)
            ->assertSee('home-market-logo-mark', false);
    }

    public function test_the_link_passes_no_ranking_signal_and_keeps_the_tab(): void
    {
        $this->configure();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s+href="https:\/\/www\.naturabuy\.fr\/boutique-armo"[^>]*rel="nofollow noopener"/s',
            preg_replace('/\s+/', ' ', $html),
        );
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function test_nothing_shows_while_the_switch_is_off(): void
    {
        $this->configure(['naturabuy_on_home' => false]);

        $this->get('/')->assertOk()->assertDontSee('home-market', false);
    }

    public function test_a_claim_without_its_figures_is_not_made(): void
    {
        // The block states a standing, so it needs the standing, the count it
        // rests on, and the page a visitor can check it against.
        foreach (['naturabuy_url' => null, 'naturabuy_rating_tenths' => null, 'naturabuy_reviews' => null] as $field => $value) {
            $this->configure([$field => $value]);

            $this->get('/')->assertOk()->assertDontSee('home-market', false);
        }
    }

    public function test_the_rating_survives_the_round_trip_through_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/settings/marketplaces', [
                'naturabuy_url' => 'https://www.naturabuy.fr/boutique-armo',
                'naturabuy_rating' => '4.9',
                'naturabuy_reviews' => '127',
                'naturabuy_on_home' => '1',
            ])->assertRedirect();

        // Kept in tenths, so no float rounds 4,9 into 4,8999 on the way out.
        $this->assertSame(49, MarketplaceSetting::current()->naturabuy_rating_tenths);
        $this->assertSame(4.9, MarketplaceSetting::current()->naturabuyRating());
    }
}
