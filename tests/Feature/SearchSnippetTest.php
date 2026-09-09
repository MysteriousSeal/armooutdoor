<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a search engine is allowed to quote off a page.
 *
 * The blurb in the footer sits on every page of the site, and Google was
 * quoting it as the snippet for category pages in place of the description
 * those pages set for themselves. It is withheld from snippets now. None of
 * this is visible on the page, so nothing but a test would notice it going.
 */
class SearchSnippetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_blurb_is_withheld_from_search_snippets(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $start = strpos($html, '<div data-nosnippet>');

        $this->assertNotFalse($start, 'The footer blurb is no longer wrapped in a nosnippet block.');

        // Inside the wrapper rather than merely somewhere after it: Google
        // honours the attribute on div, span and section only, so a paragraph
        // that drifts back out of the wrapper becomes quotable again.
        $wrapper = substr($html, $start, strpos($html, '</div>', $start) - $start);

        $this->assertSame(2, substr_count($wrapper, 'class="site-footer-about"'));
        $this->assertStringContainsString(__('store.footer_about'), $wrapper);
        $this->assertStringContainsString(__('store.footer_about_more'), $wrapper);
    }

    /**
     * The page's own description is not withheld along with it. Excluding the
     * footer is only worth doing if what should be quoted stays quotable.
     */
    public function test_a_category_keeps_its_own_description_quotable(): void
    {
        $category = Category::query()->create([
            'slug' => 'vetements-test',
            'name' => ['fr' => 'Vêtements'],
            'description' => ['fr' => 'Une description bien à elle.'],
            'sort_order' => 1,
        ]);

        $html = $this->get('/categories/'.$category->slug)->assertOk()->getContent();

        $start = strpos($html, '<div data-nosnippet>');
        $wrapper = substr($html, $start, strpos($html, '</div>', $start) - $start);

        $this->assertStringContainsString('Une description bien à elle.', $html);
        $this->assertStringNotContainsString('Une description bien à elle.', $wrapper);
    }
}
