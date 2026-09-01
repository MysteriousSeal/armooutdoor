<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The title block of the legal and help pages.
 *
 * The legal pages had no h1 at all: their title was an h2, and the only h1 on
 * the page was the wordmark in the site header until that stopped being a
 * heading. They also printed now() as their revision date, so they claimed to
 * have been rewritten on whatever day somebody happened to read them.
 */
class PagePanelHeaderTest extends TestCase
{
    use RefreshDatabase;

    public static function legalPages(): array
    {
        return [
            'cgv' => ['/cgv', 'terms'],
            'mentions' => ['/mentions-legales', 'notice'],
            'confidentialite' => ['/confidentialite', 'privacy'],
            'retractation' => ['/droit-de-retractation', 'withdrawal'],
        ];
    }

    public static function allPages(): array
    {
        return self::legalPages() + [
            'livraison' => ['/livraison-et-retours', null],
            'paiement' => ['/paiement-securise', null],
        ];
    }

    /**
     * The same pages, addresses only.
     *
     * PHPUnit warns when a data set passes more arguments than the test
     * accepts, so a method that only needs the URL is fed only the URL.
     */
    public static function urls(): array
    {
        return array_map(fn (array $row): array => [$row[0]], self::allPages());
    }

    #[DataProvider('urls')]
    public function test_each_page_is_headed_once_by_its_own_title(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#<h1[^>]*>#', $html), $url.' should have exactly one h1.');
        $this->assertStringContainsString('<h1 class="panel-header-title">', $html);
    }

    #[DataProvider('urls')]
    public function test_each_page_says_what_it_is_for(string $url): void
    {
        $this->get($url)->assertOk()->assertSee('class="panel-header-lede"', false);
    }

    #[DataProvider('legalPages')]
    public function test_a_legal_page_dates_itself_from_configuration(string $url, string $key): void
    {
        // Not now(): a page that claims to have been revised today, every day,
        // tells a customer nothing about the terms they agreed to.
        config(['shop.legal_updated.'.$key => '2025-03-04']);

        $this->get($url)->assertOk()->assertSee('4 mars 2025', false);
    }

    public function test_the_help_pages_carry_no_revision_date(): void
    {
        $this->get('/livraison-et-retours')->assertOk()->assertDontSee('panel-header-meta', false);
    }

    public function test_no_page_invents_its_own_date_any_more(): void
    {
        $this->assertStringNotContainsString(
            'now()',
            file_get_contents(resource_path('views/legal/partials/chrome.blade.php')),
        );
    }
}
