<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide to air rifle pellets: head shapes, calibre, weight against the
 * rifle's energy, head diameter, testing in one's own barrel, lead-free, and
 * lead hygiene.
 */
class GuidePlombsPageTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->get('/guides/quel-plomb-carabine-air')->assertOk()->getContent();
    }

    private function guide(): string
    {
        preg_match('/<main.*?<\/main>/s', $this->page(), $main);

        return $main[0] ?? '';
    }

    public function test_it_names_the_four_heads_and_the_calibres(): void
    {
        $guide = $this->guide();

        foreach (['Tête plate', 'Tête ronde', 'Tête pointue', 'Tête creuse', '4,5 mm', '5,5 mm', '6,35 mm'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }

        $this->assertSame(4, substr_count($guide, 'data-glab-head='));
    }

    public function test_it_carries_the_figures_the_sources_give(): void
    {
        $guide = $this->guide();

        foreach (['64,79891', '900 FPS', '7.4.6', '4,49 à 4,50 mm', '4,50 à 4,52 mm', '5,53 à 5,55 mm', '78 %', '0,030', '12 ft.lbs', '10 juillet 2026', '10 octobre 2026', 'quatre fois', 'R311-2'] as $figure) {
            $this->assertStringContainsString($figure, $guide);
        }
    }

    public function test_the_worked_figures_follow_from_the_formula(): void
    {
        $guide = $this->guide();

        // v = √(2E / m) at 19 J: the same Field Target Trophy in both calibres.
        $this->assertStringContainsString('855 FPS', $guide);
        $this->assertStringContainsString('656 FPS', $guide);

        // 8,18 grains is 0,53 g, and the calculator opens on that tin at 19 J.
        $this->assertStringContainsString('0,53 g', $guide);
        $this->assertStringContainsString('7,79 gr', $guide);
    }

    public function test_it_says_air_guns_are_not_for_animals(): void
    {
        $guide = $this->guide();

        $this->assertStringContainsString('arrêté du 1er août 1986', $guide);
        $this->assertStringContainsString('interdit pour la chasse de tout', $guide);
    }

    public function test_it_tells_the_reader_to_wash_their_hands(): void
    {
        $guide = $this->guide();

        $this->assertStringContainsString('lavez-vous les mains', $guide);
        $this->assertStringContainsString('INRS', $guide);
    }

    public function test_the_bench_is_wired_and_the_page_reads_without_javascript(): void
    {
        $guide = $this->guide();
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString("document.querySelector('[data-glab-plombs]')", $js);

        // The calculator ships hidden and the script reveals it, so nothing
        // promises a control that cannot work.
        $this->assertMatchesRegularExpression('/<section[^>]*data-glab-plombs[^>]*hidden/', $guide);

        // The same answer without script: the floor table, every energy row.
        $this->assertStringContainsString('data-glab-floor-table', $guide);
        $this->assertSame(5, preg_match_all('/<th scope="row">/', $guide));

        // Three usages to pick from, one card each.
        $this->assertSame(3, substr_count($guide, 'data-glab-usage-card='));
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->page();

        $this->assertSame(15, substr_count($html, 'glab-source-num'));
        $this->assertSame(15, substr_count($html, 'rel="noopener nofollow"'));
        $this->assertStringContainsString('legifrance.gouv.fr', $html);
        $this->assertStringContainsString('inrs.fr', $html);
        $this->assertStringContainsString('"citation"', $html);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->page();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.plombs').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertSame(1, substr_count($this->guide(), '<h1'));

        preg_match('/<title>(.*?)<\/title>/s', $html, $title);
        preg_match('/<meta name="description" content="([^"]*)"/', $html, $description);

        $this->assertLessThan(60, mb_strlen(html_entity_decode(trim($title[1] ?? ''), ENT_QUOTES)));

        $length = mb_strlen(html_entity_decode($description[1] ?? '', ENT_QUOTES));
        $this->assertGreaterThanOrEqual(120, $length);
        $this->assertLessThanOrEqual(155, $length);
    }

    public function test_the_hero_has_no_picture(): void
    {
        $this->assertStringNotContainsString('cat-hero--plate', $this->page());
    }

    public function test_the_view_never_uses_an_em_dash(): void
    {
        $this->assertStringNotContainsString("\u{2014}", file_get_contents(resource_path('views/guides/plombs.blade.php')));
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.plombs'), array_column(Guides::all(), 'url'));
        $this->assertSame('guides.plombs', Guides::forCategory('plombs-et-billes-d-acier')['route'] ?? null);

        $this->get('/guides')->assertOk()->assertSee(route('guides.plombs'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.plombs'), false);
    }
}
