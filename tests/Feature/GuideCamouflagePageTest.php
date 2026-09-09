<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le guide du camouflage.
 *
 * Two things it must not lose. The selector is revealed by its script, so a
 * visitor without JavaScript has to find every family open with its terrains
 * and its seasons in words, not a panel that never appears. And the seven
 * families are written once: the selector reads the same rows the page
 * prints, so a family added to one is added to both.
 */
class GuideCamouflagePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_guide_is_published(): void
    {
        $this->get(route('guides.camouflage'))->assertOk()
            ->assertSee('Choisir son camouflage')
            ->assertSee('Sept familles', false);
    }

    public function test_it_is_on_the_index_and_in_the_sitemap(): void
    {
        $this->get(route('guides.index'))->assertOk()
            ->assertSee('Choisir son camouflage')
            ->assertSee(route('guides.camouflage'), false);

        $this->get('/sitemap-guides.xml')->assertOk()
            ->assertSee(route('guides.camouflage'), false);
    }

    /**
     * The selector ships closed and its script opens it. A page that shipped
     * it open would offer a control that never answers where JavaScript is
     * off, which is the one thing the guides do not do.
     */
    public function test_the_selector_is_closed_until_its_script_opens_it(): void
    {
        $html = $this->get(route('guides.camouflage'))->assertOk()->getContent();

        $this->assertStringContainsString('data-cam-picker hidden', $html);
        $this->assertStringContainsString('js/guides.js', $html);
    }

    /**
     * Without JavaScript the families are the guide: every one of the seven
     * open, each carrying the terrains and the seasons the selector would
     * have sorted it by.
     */
    public function test_every_family_stands_on_its_own_without_the_selector(): void
    {
        $html = $this->get(route('guides.camouflage'))->assertOk()->getContent();

        $this->assertSame(7, substr_count($html, 'data-cam-family'));

        foreach (['CE', 'Woodland', 'Multicam', 'A-TACS', 'Numérique', 'Désert', 'Mimétique animal'] as $family) {
            $this->assertStringContainsString($family, $html, $family.' is missing from the guide');
        }

        // The words, not only the data attributes the script reads.
        $this->assertStringContainsString('Sous-bois dense', $html);
        $this->assertStringContainsString('Printemps', $html);
    }

    /**
     * Terrains and seasons carry their own turn of phrase, because the
     * verdict is a sentence: gluing a preposition to a label in JavaScript
     * produced "au été".
     */
    public function test_each_choice_carries_the_phrase_the_verdict_needs(): void
    {
        $html = $this->get(route('guides.camouflage'))->assertOk()->getContent();

        $this->assertStringContainsString('data-phrase="en sous-bois dense"', $html);
        $this->assertStringContainsString('data-phrase="en été"', $html);
        $this->assertStringContainsString('data-phrase="au printemps"', $html);
    }

    public function test_the_guide_answers_for_snow_rather_than_ranking_seven_wrong_answers(): void
    {
        // None of the seven is a winter pattern, and the page says so in
        // prose as well as in the script, since the prose is what a reader
        // without JavaScript gets.
        $this->get(route('guides.camouflage'))->assertOk()
            ->assertSee('Aucune de ces sept familles ne vaut sur la neige', false);
    }

    public function test_it_sends_the_reader_to_what_covers_the_skin(): void
    {
        // The guide's own conclusion is that the face and the hands are what
        // is left, so the four rayons that cover them are one click away.
        $html = $this->get(route('guides.camouflage'))->assertOk()->getContent();

        foreach (['cache-cou', 'cagoules', 'gants', 'casquettes'] as $slug) {
            $this->assertStringContainsString('/categories/'.$slug, $html, $slug.' is not linked');
        }
    }

    /**
     * Every guide's structured data must carry @context.
     *
     * Blade owns the at sign: a bare '@context' key in a Blade file compiles
     * as a directive, and this guide shipped three JSON-LD blocks whose first
     * key was a fragment of PHP source. Google discards a block without a
     * context, so the page had an Article, a FAQPage and a BreadcrumbList that
     * no crawler could read. Checked across the shelf, since the mistake is
     * one keystroke away on any of them.
     */
    public function test_every_guides_structured_data_declares_its_context(): void
    {
        $urls = array_merge([route('guides.index')], array_column(Guides::all(), 'url'));

        foreach ($urls as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

            $this->assertNotEmpty($blocks[1], $url.' publishes no structured data');

            foreach ($blocks[1] as $block) {
                $decoded = json_decode($block, true);

                $this->assertIsArray($decoded, $url.' has structured data that is not valid JSON');
                $this->assertSame('https://schema.org', $decoded['@context'] ?? null, $url);
            }
        }
    }

    /**
     * Every family shows the cloth. The page used to draw its own patterns in
     * gradients, which could suggest a palette and a blob size and no more; a
     * family whose photograph is missing would now render an empty band, and
     * the reader would be asked to choose a motif without seeing one.
     */
    public function test_every_family_shows_a_photograph_of_its_cloth(): void
    {
        $html = $this->get(route('guides.camouflage'))->assertOk()->getContent();

        $keys = ['ce', 'woodland', 'multicam', 'atacs', 'digital', 'desert', 'python'];

        foreach ($keys as $key) {
            $path = 'images/guides/camouflage/'.$key.'.webp';

            $this->assertStringContainsString($path, $html, $key.' has no swatch on the page');
            $this->assertFileExists(public_path($path));
        }

        // Seven families, seven photographs, none of them decorative: the
        // whole point of the page is what a motif looks like, so a reader who
        // cannot see it is owed the colours and the shape of the blobs.
        $this->assertSame(count($keys), substr_count($html, 'class="cam-swatch"'));
        $this->assertSame(count($keys), substr_count($html, 'loading="lazy"'));
        $this->assertSame(count($keys), preg_match_all('/alt="Tissu au motif [^"]{20,}"/', $html));
    }

    /**
     * One sheet holds every guide, fenced into a named section each. This
     * reads back one guide's fence, so an assertion about what this page
     * declares cannot be satisfied by a rule another guide wrote.
     */
    private function cssSection(string $slug): string
    {
        $css = file_get_contents(public_path('css/guides.css'));

        $start = strpos($css, '/* ============================== '.$slug.' ');
        $this->assertNotFalse($start, 'guides.css has no section for '.$slug);

        $next = strpos($css, '/* ============================== ', $start + 1);

        return $next === false ? substr($css, $start) : substr($css, $start, $next - $start);
    }

    /**
     * The guide reads in the shop's colours, like every other guide.
     *
     * It spent a while in a palette of its own, a dark band and a blaze
     * accent, which made it look like a different site rather than like one
     * more thing the shop publishes. Its sheet describes the shapes no other
     * guide has and decides none of their colours.
     */
    public function test_it_declares_no_palette_of_its_own(): void
    {
        $css = $this->cssSection('camouflage');

        // No hex, no theme block, and no band giving up the shop's container.
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}/', $css);
        $this->assertStringNotContainsString('data-theme', $css);
        $this->assertStringNotContainsString('max-width: none', $css);

        // The colours it does use are the ones every guide uses.
        $this->assertStringContainsString('var(--accent-heading)', $css);
    }

    public function test_the_shelf_knows_about_it(): void
    {
        $urls = collect(Guides::all())->pluck('url');

        $this->assertTrue($urls->contains(route('guides.camouflage')));
    }
}
