<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide on zeroing a scope: the one page of the shelf whose whole value
 * is a number, and therefore the one where a wrong number would be worse
 * than no page at all.
 */
class GuideOptiquePageTest extends TestCase
{
    use RefreshDatabase;

    private function guide(): string
    {
        $html = $this->get('/guides/regler-sa-lunette')->assertOk()->getContent();

        preg_match('/<main.*?<\/main>/s', $html, $main);

        return $main[0] ?? '';
    }

    public function test_the_page_answers_the_question_it_is_named_after(): void
    {
        $guide = $this->guide();

        foreach (['MOA', 'MRAD', 'clic', 'parallaxe', 'groupement', 'tourelle'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }

        // The thesis: a zero is a distance. A page that never says so is a
        // page about turrets, which the web already has enough of.
        $this->assertStringContainsString('est une distance', $guide);
    }

    public function test_it_prints_the_two_angles_the_whole_page_rests_on(): void
    {
        $guide = $this->guide();

        // One minute of angle subtends 2,908 cm at 100 m; one milliradian
        // subtends exactly 10 cm there. Every other figure on the page, and
        // every answer the converter gives, is derived from these two.
        $this->assertStringContainsString('2,908 cm', $guide);
        $this->assertStringContainsString('10 cm à 100 mètres', $guide);
    }

    /**
     * The converter answers before the script runs, and answers correctly.
     *
     * The block is rendered server-side with a default so the page says
     * something true without JavaScript. That default is a second copy of
     * the arithmetic in public/js/guides.js, and a second copy is exactly
     * the thing that drifts. So nothing here is written twice: the inputs
     * are read off the markup, the angle is read off the script, and the
     * printed answer is compared against what the two produce together.
     */
    public function test_the_converter_default_is_the_answer_the_script_would_give(): void
    {
        $guide = $this->guide();
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString('data-glab-clicks', $guide);

        $field = function (string $attribute) use ($guide): float {
            $this->assertMatchesRegularExpression(
                '/value="([0-9.]+)" '.preg_quote($attribute, '/').'/',
                $guide,
                $attribute.' carries no default',
            );

            preg_match('/value="([0-9.]+)" '.preg_quote($attribute, '/').'/', $guide, $found);

            return (float) $found[1];
        };

        // The turret the markup has selected, and what its unit is worth at
        // a hundred metres according to the script itself.
        preg_match('/<option value="([0-9.]+)\|(moa|mrad)"/', $guide, $turret);
        preg_match('/var '.strtoupper($turret[2]).'_AT_100 = ([0-9.]+);/', $js, $angle);

        $this->assertNotEmpty($angle, 'the script names no angle for '.$turret[2]);

        $metres = $field('data-glab-distance');
        $perClick = (float) $turret[1] * (float) $angle[1] * ($metres / 100);

        foreach (['drop' => 'drop-count', 'drift' => 'drift-count'] as $input => $output) {
            $clicks = (int) round($field('data-glab-'.$input) / $perClick);

            $this->assertStringContainsString(
                'data-glab-'.$output.'>'.$clicks.'<',
                $guide,
                'the printed '.$input.' default is not what the script would compute',
            );
        }

        // Two turrets, two answers: a single number would be half a zero.
        $this->assertSame(2, substr_count($guide, 'glab-click-count'));
        $this->assertStringContainsString('vers le haut', $guide);
        $this->assertStringContainsString('vers la droite', $guide);

        // And the ladder that explains why the numbers are that large: it
        // was rendered outside the mount once, and kept a value the script
        // would never have printed.
        preg_match_all('/data-glab-rung>([^<]+)</', $guide, $rungs);

        $this->assertCount(4, $rungs[1]);

        foreach ([10, 25, 50, 100] as $index => $rung) {
            $cm = (float) $turret[1] * (float) $angle[1] * ($rung / 100);

            $this->assertSame(
                $cm < 1
                    ? number_format($cm * 10, 1, ',', '').' mm'
                    : number_format($cm, 2, ',', '').' cm',
                $rungs[1][$index],
                'the '.$rung.' m rung is not what the script would print',
            );
        }
    }

    public function test_the_converter_is_driven_by_the_shared_script(): void
    {
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString("querySelector('[data-glab-clicks]')", $js);

        // The two constants, written once in the script rather than folded
        // into the formulas where nobody would find them again.
        $this->assertStringContainsString('var MOA_AT_100 = 2.908;', $js);
        $this->assertStringContainsString('var MRAD_AT_100 = 10;', $js);
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->get('/guides/regler-sa-lunette')->assertOk()->getContent();

        $this->assertSame(9, substr_count($html, 'glab-source-num'));
        $this->assertSame(9, substr_count($html, 'rel="noopener nofollow"'));
        $this->assertStringContainsString('"citation"', $html);
    }

    public function test_it_sends_readers_to_the_rayons_the_shop_actually_stocks(): void
    {
        $guide = $this->guide();

        // The shop sells no riflescope. It sells what makes a zero legible,
        // and the page says so rather than inventing a rayon to link to.
        $this->assertStringContainsString('ne vend pas de lunette', $guide);

        foreach (['cibles-carrees', 'pastilles-autocollantes', 'longues-vues', 'telemetres', 'optiques'] as $slug) {
            $this->assertStringContainsString(route('categories.show', $slug), $guide, $slug.' is not linked');
        }
    }

    public function test_it_does_not_re_explain_what_the_other_guides_own(): void
    {
        $guide = $this->guide();

        // « Bien choisir sa cible » owns the formats and the quantities,
        // « Joules et FPS » owns the energy arithmetic. This page owns the
        // angle, and links rather than repeats.
        foreach (['lots de 100', 'basculante', 'E = ½'] as $owned) {
            $this->assertStringNotContainsString($owned, $guide);
        }

        $this->assertStringContainsString(route('guides.cibles'), $guide);
        $this->assertStringContainsString(route('guides.glossaire'), $guide);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->get('/guides/regler-sa-lunette')->assertOk()->getContent();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.optique').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.optique'), array_column(Guides::all(), 'url'));

        $this->get('/guides')->assertOk()->assertSee(route('guides.optique'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.optique'), false);
    }
}
