<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide on a first visit to a range: the page written for someone who
 * has not bought anything yet, and whose best advice is to buy nothing.
 */
class GuidePremiereSeancePageTest extends TestCase
{
    use RefreshDatabase;

    private function guide(): string
    {
        $html = $this->get('/guides/premiere-seance-au-stand')->assertOk()->getContent();

        preg_match('/<main.*?<\/main>/s', $html, $main);

        return $main[0] ?? '';
    }

    public function test_the_page_answers_the_question_it_is_named_after(): void
    {
        $guide = $this->guide();

        foreach (['pièce d\'identité', 'briefing', 'témoin de chambre vide', 'carnet de tir'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }

        // The thesis, and the one thing a first-timer gets wrong.
        $this->assertStringContainsString('pas besoin d\'une arme', $guide);
    }

    public function test_it_carries_the_four_rules_and_the_three_commands(): void
    {
        $guide = $this->guide();

        // The four fundamentals, in the federation's own terms.
        $this->assertStringContainsString('considérée comme CHARGÉE', $guide);
        $this->assertStringContainsString('index', $guide);
        $this->assertStringContainsString('pontet', $guide);

        foreach (['Commencez le tir', 'Cessez le feu', 'Armes au repos'] as $command) {
            $this->assertStringContainsString($command, $guide, $command.' is missing');
        }
    }

    /**
     * The selector answers before the script runs, and it is named.
     *
     * A nameless mount is what once made the targets guide answer with
     * cleaning rods, so a new selector declares whose it is on both sides.
     */
    public function test_the_bag_selector_is_named_and_answers_without_javascript(): void
    {
        $guide = $this->guide();
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString('data-glab-selector="seance"', $guide);
        $this->assertStringContainsString('GlabSelector.mount(\'[data-glab-selector="seance"]\'', $js);

        // Two questions, and the default answer rendered server-side: four
        // cards, crawlable, before a line of script has run.
        $this->assertSame(2, substr_count($guide, 'data-glab-group='));
        $this->assertSame(4, substr_count($guide, 'glab-reco-rank'));

        // And the advice a discovery session deserves, which is to bring
        // nothing: the page refuses to sell into that visit.
        $this->assertStringContainsString('Surtout pas votre propre arme', $guide);
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->get('/guides/premiere-seance-au-stand')->assertOk()->getContent();

        $this->assertSame(8, substr_count($html, 'glab-source-num'));
        $this->assertSame(8, substr_count($html, 'rel="noopener nofollow"'));
        $this->assertStringContainsString('fftir.org', $html);
        $this->assertStringContainsString('"citation"', $html);
    }

    public function test_it_says_the_club_rules_win(): void
    {
        $guide = $this->guide();

        // A page describing « most French ranges » must say out loud that
        // the règlement intérieur of the one you walk into beats it.
        $this->assertStringContainsString('glab-warning', $guide);
        $this->assertStringContainsString('règlement', $guide);
    }

    public function test_it_does_not_re_explain_what_the_other_guides_own(): void
    {
        $guide = $this->guide();

        // « Où tirer légalement » owns the law of the place, « Classer son
        // arme » owns the categories. This page owns the visit, and links.
        foreach (['R. 312-40', 'L. 422-10', '150 mètres'] as $owned) {
            $this->assertStringNotContainsString($owned, $guide);
        }

        $this->assertStringContainsString(route('guides.ou-tirer'), $guide);
        $this->assertStringContainsString(route('guides.classification'), $guide);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->get('/guides/premiere-seance-au-stand')->assertOk()->getContent();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.premiere-seance').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.premiere-seance'), array_column(Guides::all(), 'url'));

        $this->get('/guides')->assertOk()->assertSee(route('guides.premiere-seance'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.premiere-seance'), false);
    }
}
