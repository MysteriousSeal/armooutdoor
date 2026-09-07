<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's mark beside the wordmark: drawn, not embedded, so it carries
 * no white ground onto the dark theme.
 */
class HeaderMarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mark_stands_beside_the_wordmark(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('class="site-mark"', false)
            ->assertSee('armo-ink', false)
            // A stroke and no ground, so the header shows through and the
            // mark carries no colour of its own onto either theme.
            ->assertSee('armo-ring', false);
    }

    public function test_it_repeats_no_link_for_a_screen_reader(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // It links where the wordmark beside it already links, so it is
        // hidden from assistive tech and skipped by the keyboard.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*class="site-mark-link"[^>]*aria-hidden="true"[^>]*tabindex="-1"/s',
            preg_replace('/\s+/', ' ', $html),
        );
    }

    public function test_it_is_on_every_page_not_only_the_home(): void
    {
        foreach (['/', '/produits', '/guides'] as $url) {
            $this->get($url)->assertOk()->assertSee('class="site-mark"', false);
        }
    }
}
