<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide on where one may shoot: the page of the site with the most to
 * lose from being wrong, and the one that most has to stay off the ground
 * the other guides already cover.
 */
class GuideOuTirerPageTest extends TestCase
{
    use RefreshDatabase;

    private function guide(): string
    {
        $html = $this->get('/guides/ou-tirer-legalement')->assertOk()->getContent();

        preg_match('/<main.*?<\/main>/s', $html, $main);

        return $main[0] ?? '';
    }

    public function test_the_page_answers_the_question_it_is_named_after(): void
    {
        $guide = $this->guide();

        foreach (['Chez soi', 'terrain', 'stand', 'point d\'arrêt', 'direction'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }
    }

    public function test_it_corrects_the_two_texts_the_web_cites_wrongly(): void
    {
        $guide = $this->guide();

        // R. 312-40 is about tir sportif, not gardens; the 150 m come from
        // the hunting territory rule, not from a safety distance. Both are
        // repeated across the French web, and the guide exists to say so.
        $this->assertStringContainsString('R. 312-40', $guide);
        $this->assertStringContainsString('tir sportif', $guide);
        $this->assertStringContainsString('L. 422-10', $guide);
        $this->assertStringContainsString('150 mètres', $guide);
        $this->assertSame(3, substr_count($guide, 'glab-myth-claim'));
    }

    public function test_it_names_the_texts_that_actually_govern(): void
    {
        $guide = $this->guide();

        foreach (['R. 1336-5', 'L. 2212-2'] as $article) {
            $this->assertStringContainsString($article, $guide);
        }
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->get('/guides/ou-tirer-legalement')->assertOk()->getContent();

        // A legal page that cannot be checked is worth nothing. Six sources,
        // linked out and declared to search engines as citations.
        $this->assertSame(6, substr_count($html, 'glab-source-num'));
        $this->assertStringContainsString('legifrance.gouv.fr', $html);
        $this->assertStringContainsString('"citation"', $html);
        $this->assertSame(6, substr_count($html, 'rel="noopener nofollow"'));
    }

    public function test_the_plan_is_described_for_those_who_cannot_see_it(): void
    {
        $guide = $this->guide();

        $this->assertStringContainsString('<svg viewBox="0 0 640 320" role="img"', $guide);
        $this->assertStringContainsString('<title id="lane-title">', $guide);
        $this->assertStringContainsString('<desc id="lane-desc">', $guide);
    }

    public function test_it_does_not_re_explain_what_the_other_guides_own(): void
    {
        $guide = $this->guide();

        // The categories and the joule thresholds belong to « Classer son
        // arme » and « Joules et FPS ». This page links to them instead of
        // saying it all again.
        $this->assertStringNotContainsString('2 à 20 joules', $guide);
        $this->assertStringNotContainsString('Catégorie C', $guide);
        $this->assertStringContainsString(route('guides.classification'), $guide);
        $this->assertStringContainsString(route('guides.glossaire'), $guide);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->get('/guides/ou-tirer-legalement')->assertOk()->getContent();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.ou-tirer').'">', $html);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.ou-tirer'), array_column(Guides::all(), 'url'));

        $this->get('/guides')->assertOk()->assertSee(route('guides.ou-tirer'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.ou-tirer'), false);
    }
}
