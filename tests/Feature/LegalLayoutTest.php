<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The legal pages sit on the same edges as the rest of the shop.
 *
 * They stopped at 48rem inside a container twice that wide, which left them
 * floating in the middle of the page looking like a different site. The width
 * goes to the four pages themselves rather than to the prose, which keeps a
 * measure it can be read at.
 */
class LegalLayoutTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'cgv' => ['/cgv', 'legal.terms'],
            'mentions' => ['/mentions-legales', 'legal.notice'],
            'confidentialite' => ['/confidentialite', 'legal.privacy'],
            'retractation' => ['/droit-de-retractation', 'legal.withdrawal'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_sets_the_document_beside_the_index(string $url): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('class="legal-layout"', false)
            ->assertSee('class="legal-aside"', false)
            ->assertSee('<article class="legal-doc">', false);
    }

    #[DataProvider('pages')]
    public function test_each_page_links_to_all_four_and_marks_itself(string $url, string $route): void
    {
        $response = $this->get($url)->assertOk();

        foreach (array_column(self::pages(), 1) as $sibling) {
            $response->assertSee('href="'.route($sibling).'"', false);
        }

        // The page you are on is named for a screen reader, not only coloured.
        $response->assertSee('aria-current="page"', false);
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'aria-current="page"'),
            'Exactly one legal link should be marked current.',
        );
    }

    public function test_the_document_no_longer_carries_its_own_width(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringNotContainsString('.legal-wrap {'.PHP_EOL.'    max-width: 48rem;', $css);
        $this->assertStringContainsString('.legal-layout {', $css);
    }
}
