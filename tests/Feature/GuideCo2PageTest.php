<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide for a CO2 replica that weakens in the cold: the cartridge's
 * pressure set by temperature alone, what a 12 g gives in shots, and how a
 * cartridge is oiled, stored, thrown away and carried.
 */
class GuideCo2PageTest extends TestCase
{
    use RefreshDatabase;

    private function html(): string
    {
        return $this->get('/guides/co2-et-froid')->assertOk()->getContent();
    }

    private function guide(): string
    {
        preg_match('/<main.*?<\/main>/s', $this->html(), $main);

        return $main[0] ?? '';
    }

    public function test_it_carries_the_nist_pressures_and_the_critical_point(): void
    {
        $guide = $this->guide();

        foreach (['57,3 bar', '34,9 bar', '26,5 bar', '73,8 bar', '31 °C', '152 joules', '4,7'] as $figure) {
            $this->assertStringContainsString($figure, $guide);
        }

        // Pressure follows temperature only while liquid remains, and the heat
        // per shot counts the vapour that refills the space the liquid leaves.
        $this->assertStringContainsString('Tant qu\'il reste du liquide', $guide);
        $this->assertStringContainsString('environ 30 joules', $guide);
    }

    public function test_it_gives_the_shot_counts_with_their_conditions(): void
    {
        $guide = $this->guide();

        // A shot count means nothing without the slide and the temperature.
        foreach (['83 tirs', '66 tirs', '18 °C', 'Culasse fixe', 'Culasse mobile', '88 g'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }
    }

    public function test_the_heat_warning_and_the_cartridge_gestures_survive(): void
    {
        $guide = $this->guide();

        // The warnings that must outlive every edit: heat, a pierced
        // cartridge left in the gun, and piercing an empty one.
        foreach (['48,9 °C', '50 °C', '24 à 48 heures', 'ne pas perforer, ni brûler, même après usage', 'jamais dans le bac de tri'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }
    }

    public function test_it_states_the_law_and_the_air_rules(): void
    {
        $guide = $this->guide();

        foreach (['R311-1', 'R311-2', 'catégorie D', 'catégorie C', 'UN 1013', 'approbation de la compagnie'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }
    }

    public function test_it_names_only_what_the_shop_sells(): void
    {
        $guide = $this->guide();

        $this->assertStringContainsString(route('categories.show', 'cartouches-de-co2-12g-et-88g'), $guide);
        $this->assertStringContainsString(route('categories.show', 'repliques-de-poing'), $guide);
        $this->assertStringContainsString(route('products.show', 'pistolet-ruger-p345-co2-culasse-fixe-6mm-airsoft'), $guide);
        $this->assertStringContainsString(route('products.show', 'revolver-dan-wesson-715-nickele-4-pouces-6-mm-airsoft'), $guide);
        $this->assertStringContainsString(route('guides.transport'), $guide);
        $this->assertStringContainsString(route('guides.classification'), $guide);
    }

    public function test_the_gauge_is_wired_and_the_page_reads_in_full_without_javascript(): void
    {
        $guide = $this->guide();
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString("document.querySelector('[data-glab-co2]')", $js);

        // The gauge waits for the script; the table and the bands do not.
        $this->assertMatchesRegularExpression('/<section[^>]*data-glab-co2[^>]*hidden/', $guide);
        $this->assertSame(11, substr_count($guide, 'data-co2-row'));
        $this->assertSame(5, substr_count($guide, 'data-co2-zone="'));
        $this->assertStringContainsString('data-t="20" data-bar="57.291"', $guide);

        preg_match_all('/<(?:tr|div)[^>]*data-co2-(?:row|zone=)[^>]*>/', $guide, $static);
        $this->assertCount(16, $static[0]);

        foreach ($static[0] as $element) {
            $this->assertStringNotContainsString('hidden', $element);
        }
    }

    public function test_it_is_honest_about_what_the_gauge_does_not_say(): void
    {
        $guide = $this->guide();

        $this->assertStringContainsString('aucun chiffre de vitesse n\'est avancé ici', $guide);
        $this->assertStringContainsString('interpolée', $guide);
    }

    public function test_no_em_dash_and_no_hero_picture(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString("\u{2014}", $html);
        $this->assertStringNotContainsString('cat-hero--plate', $html);
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->html();

        $this->assertSame(14, substr_count($html, 'glab-source-num'));
        $this->assertSame(14, substr_count($html, 'rel="noopener nofollow"'));
        $this->assertStringContainsString('webbook.nist.gov', $html);
        $this->assertStringContainsString('legifrance.gouv.fr', $html);
        $this->assertStringContainsString('dgr-67-fr-2.3.a.pdf', $html);
        $this->assertStringContainsString('"citation"', $html);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->html();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.co2').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);

        preg_match('/<title>(.*?)<\/title>/s', $html, $title);
        $this->assertLessThan(60, mb_strlen(html_entity_decode(trim($title[1] ?? ''), ENT_QUOTES)));

        preg_match('/<meta name="description" content="([^"]*)"/', $html, $description);
        $length = mb_strlen(html_entity_decode($description[1] ?? '', ENT_QUOTES));
        $this->assertGreaterThanOrEqual(120, $length);
        $this->assertLessThanOrEqual(155, $length);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.co2'), array_column(Guides::all(), 'url'));
        $this->assertSame(Guides::byRoute('guides.co2'), Guides::forCategory('cartouches-de-co2-12g-et-88g'));

        $this->get('/guides')->assertOk()->assertSee(route('guides.co2'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.co2'), false);
    }
}
