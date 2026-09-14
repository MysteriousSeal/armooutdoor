<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide to sound moderators: what French law allows, what a moderator
 * really takes off a shot, where hearing damage starts, and an estimate of
 * the level at the ear.
 */
class GuideModerateurPageTest extends TestCase
{
    use RefreshDatabase;

    private function guide(): string
    {
        $html = $this->get('/guides/moderateur-de-son')->assertOk()->getContent();

        preg_match('/<main.*?<\/main>/s', $html, $main);

        return $main[0] ?? '';
    }

    public function test_it_cites_the_texts_that_decide_the_legal_status(): void
    {
        $guide = $this->guide();

        foreach (['R311-1', 'R312-45-2', 'R312-53', 'arrêté du 2 janvier 2018', '23 janvier 2018', '1er août 2018', 'pièces additionnelles ne modifiant pas le fonctionnement'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }

        // Not free for every adult: the purchase asks for a title and the gun's own.
        $this->assertStringContainsString('pas en vente libre', $guide);
    }

    public function test_it_carries_the_figures_the_sources_give(): void
    {
        $guide = $this->guide();

        foreach (['17 à 24 dB', '9 à 21 dB', '20 à 28 dB', '150 à 165', '144 dB', '172 dB', '80 dB(A)', '135 dB(C)', '137 dB(C)', '140 dB(C)', '87 dB(A)', '40 heures', '5 à 10 dB', '24 heures', '1/2 UNF', 'M14x1', 'M18x1'] as $figure) {
            $this->assertStringContainsString($figure, $guide);
        }
    }

    public function test_it_is_honest_about_the_model_and_the_shelf(): void
    {
        $guide = $this->guide();

        $this->assertStringContainsString('ordres de grandeur', $guide);
        $this->assertStringContainsString('La boutique ne vend pas de modérateur de son', $guide);
        $this->assertStringContainsString('pas trouvé de texte officiel qui tranche', $guide);
        $this->assertStringContainsString('/categories/accessoires-silencieux-moderateurs', $guide);
    }

    public function test_the_calculator_is_wired_and_the_table_reads_without_javascript(): void
    {
        $guide = $this->guide();
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString("document.querySelector('[data-glab-moderateur]')", $js);

        // Three guns, two moderator states, three protections.
        $this->assertSame(8, substr_count($guide, 'data-glab-db-value='));
        $this->assertSame(1, substr_count($guide, 'data-glab-db-band'));

        // The calculator is hidden until the script reveals it, and the
        // table that answers for everyone else is not inside it.
        preg_match('/<section[^>]*data-glab-moderateur[^>]*>.*?<\/section>/s', $guide, $calculator);
        $this->assertNotEmpty($calculator);
        $this->assertMatchesRegularExpression('/<section[^>]*data-glab-moderateur[^>]*hidden/', $guide);
        $this->assertStringNotContainsString('<table', $calculator[0]);

        $this->assertStringContainsString('<table class="glab-table">', $guide);
        $this->assertSame(3, substr_count($guide, '<th scope="row">'));
    }

    public function test_the_table_computes_the_ranges_from_the_measurements(): void
    {
        $guide = $this->guide();

        // A common gun, bare ears: 150 to 165. With a moderator: 126 to 148.
        // With a moderator and SNR 30: 96 to 118.
        $this->assertStringContainsString('150 à 165 dB</td>', $guide);
        $this->assertStringContainsString('126 à 148 dB</td>', $guide);
        $this->assertStringContainsString('96 à 118 dB</td>', $guide);
    }

    public function test_it_never_uses_an_em_dash(): void
    {
        $this->assertStringNotContainsString("\u{2014}", $this->guide());
        $this->assertStringNotContainsString("\u{2014}", file_get_contents(resource_path('views/guides/moderateur.blade.php')));
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->get('/guides/moderateur-de-son')->assertOk()->getContent();

        $this->assertSame(15, substr_count($html, 'glab-source-num'));
        $this->assertSame(15, substr_count($html, 'rel="noopener nofollow"'));
        $this->assertStringContainsString('legifrance.gouv.fr', $html);
        $this->assertStringContainsString('stacks.cdc.gov', $html);
        $this->assertStringContainsString('inrs.fr', $html);
        $this->assertStringContainsString('"citation"', $html);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->get('/guides/moderateur-de-son')->assertOk()->getContent();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.moderateur').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertSame(1, substr_count($this->guide(), '<h1'));

        preg_match('/<meta name="description" content="([^"]*)"/', $html, $description);
        $length = mb_strlen(html_entity_decode($description[1] ?? '', ENT_QUOTES));
        $this->assertGreaterThanOrEqual(120, $length);
        $this->assertLessThanOrEqual(155, $length);
    }

    public function test_the_hero_has_no_picture(): void
    {
        $html = $this->get('/guides/moderateur-de-son')->assertOk()->getContent();

        $this->assertStringNotContainsString('cat-hero--plate', $html);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.moderateur'), array_column(Guides::all(), 'url'));
        $this->assertSame('guides.moderateur', Guides::forCategory('accessoires-silencieux-moderateurs')['route'] ?? null);

        $this->get('/guides')->assertOk()->assertSee(route('guides.moderateur'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.moderateur'), false);
    }
}
