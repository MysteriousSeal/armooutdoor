<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The joules guide: a conversion the whole rayon depends on, and the one
 * page of the shop that does arithmetic in front of the reader.
 */
class GuideJoulesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_answers_the_question_it_is_named_after(): void
    {
        $this->get('/guides/joules-et-fps')
            ->assertOk()
            ->assertSee('Joules et FPS')
            ->assertSee('class="glab-formula-line"', false)
            // The thresholds the law is written with.
            ->assertSee('2 J')
            ->assertSee('20 J')
            // And the way out, into the rayons the guide advises on.
            ->assertSee(route('categories.show', 'repliques-airsoft'), false)
            ->assertSee(route('guides.classification'), false);
    }

    public function test_the_reference_table_computes_the_energy_correctly(): void
    {
        $html = $this->get('/guides/joules-et-fps')->assertOk()->getContent();

        preg_match('/<tr>\s*<th scope="row">0,20 g<\/th>(.*?)<\/tr>/s', $html, $row);
        $cells = [];
        preg_match_all('/<td[^>]*>([^<]+)<\/td>/', $row[1] ?? '', $cells);

        // 0,20 g at 350 FPS is 1,14 joule: the figure every French field
        // caps its AEGs at, and the one the prose leans on throughout.
        $this->assertSame('0,73', $cells[1][0] ?? null, '280 FPS');
        $this->assertSame('1,14', $cells[1][3] ?? null, '350 FPS');
        $this->assertSame('1,88', $cells[1][5] ?? null, '450 FPS');
    }

    public function test_the_calculator_ships_closed_and_the_table_does_not(): void
    {
        $html = $this->get('/guides/joules-et-fps')->assertOk()->getContent();

        // A control that cannot work without JavaScript must not be offered
        // by a page that has none; the table answers the same question.
        $this->assertMatchesRegularExpression('/<section[^>]*data-glab-joules[^>]*\shidden/', $html);
        $this->assertStringContainsString('js/guides.js', $html);
        $this->assertStringContainsString('<table class="glab-table">', $html);
    }

    public function test_the_table_shades_towards_the_legal_line(): void
    {
        $html = $this->get('/guides/joules-et-fps')->assertOk()->getContent();

        preg_match('/<tr>\s*<th scope="row">0,20 g<\/th>(.*?)<\/tr>/s', $html, $row);

        // 0,73 J is half the legal limit and carries no tint; 2,32 J has
        // crossed it and says so rather than sitting among the others.
        $this->assertStringContainsString('<td class="">0,73</td>', $row[1]);
        $this->assertStringContainsString('<td class="is-over">2,32</td>', $row[1]);
    }

    public function test_the_energy_scale_carries_a_needle_only_where_something_is_measured(): void
    {
        $html = $this->get('/guides/joules-et-fps')->assertOk()->getContent();

        // Two scales: the calculator's, which points at a result, and the
        // one in the seuils, which only names the three regimes.
        $this->assertSame(2, substr_count($html, 'data-glab-scale'));
        $this->assertSame(1, substr_count($html, 'glab-scale-needle'));

        // The three field caps, ticked on the calculator's scale alone, and
        // braced under a single label: they fall within seven per cent of
        // each other, so three labels would collide and three bare ticks
        // would say nothing.
        $this->assertSame(3, substr_count($html, 'class="glab-scale-cap"'));
        $this->assertSame(1, substr_count($html, 'glab-scale-caps-brace'));
        $this->assertStringContainsString('Limites de terrain', $html);

        foreach (['Hors catégorie', 'Catégorie D', 'Catégorie C'] as $zone) {
            $this->assertStringContainsString('>'.$zone.'</span>', $html);
        }
    }

    public function test_the_prose_stands_on_the_page_and_the_instruments_in_panels(): void
    {
        $html = $this->get('/guides/joules-et-fps')->assertOk()->getContent();

        // The calculator, the table, the seuils and the questions are boxed;
        // the three prose sections are not, so the tools read as tools.
        $this->assertSame(4, substr_count($html, '<section class="glab-panel'));
        $this->assertSame(3, substr_count($html, '<section class="glab-section"'));
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->get('/guides/joules-et-fps')->assertOk()->getContent();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.joules').'">', $html);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap_read(): void
    {
        $urls = array_column(Guides::all(), 'url');

        $this->assertContains(route('guides.joules'), $urls);

        $this->get('/guides')->assertOk()->assertSee(route('guides.joules'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.joules'), false);
    }
}
