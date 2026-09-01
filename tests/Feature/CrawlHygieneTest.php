<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a crawler is asked to skip.
 *
 * A results page is assembled per visitor and worth nothing to anybody
 * arriving cold, and a search engine can invent as many of them as it likes.
 */
class CrawlHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_search_results_page_asks_not_to_be_indexed(): void
    {
        // Assembled per visitor, and « cible » and « cibles » are two
        // addresses holding the same products.
        $this->get('/search?q=cible')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_it_still_lets_a_crawler_follow_through_to_the_products(): void
    {
        $this->get('/search?q=cible')->assertOk()->assertDontSee('nofollow', false);
    }

    public function test_robots_txt_does_not_block_the_page_that_says_so(): void
    {
        // A page disallowed in robots.txt is never fetched, so the noindex on
        // it is never read. The two would cancel out.
        $this->get('/robots.txt')->assertOk()->assertDontSee('Disallow: /search');
    }

    public function test_an_ordinary_page_asks_for_nothing(): void
    {
        $this->get('/')->assertOk()->assertDontSee('name="robots"', false);
    }
}
