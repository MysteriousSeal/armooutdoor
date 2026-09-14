<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide for a weapon holder whose data has leaked: what each of the four
 * leaks exposed, why the address is the target, and what stays under the
 * reader's control.
 */
class GuideDonneesPageTest extends TestCase
{
    use RefreshDatabase;

    private function guide(): string
    {
        $html = $this->get('/guides/proteger-ses-donnees')->assertOk()->getContent();

        preg_match('/<main.*?<\/main>/s', $html, $main);

        return $main[0] ?? '';
    }

    public function test_the_page_names_the_four_leaks_with_their_dates(): void
    {
        $guide = $this->guide();

        foreach (['FFTir', 'SIA', 'Armurerie Lavaux', 'NaturaBuy', '18 au 20 octobre 2025', '26 mars 2026', '1er août 2026', '10 septembre 2026'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }
    }

    public function test_it_carries_the_figures_the_sources_give(): void
    {
        $guide = $this->guide();

        foreach (['274 000', '62 511', '20 à 30 cambriolages', '1er janvier 2025', 'R314-4'] as $figure) {
            $this->assertStringContainsString($figure, $guide);
        }
    }

    public function test_it_says_the_police_never_come_for_the_weapons(): void
    {
        $guide = $this->guide();

        // The one warning that must survive every edit: fake officers turned
        // up at shooters' homes after the FFTir leak.
        $this->assertStringContainsString('ne viendront jamais spontanément', $guide);
        $this->assertStringContainsString('matricule à sept chiffres', $guide);
        $this->assertStringContainsString('composez le 17', $guide);
    }

    public function test_it_is_open_about_the_shop_selling_on_naturabuy(): void
    {
        $this->assertStringContainsString('Nous vendons également sur NaturaBuy', $this->guide());
    }

    public function test_the_checker_is_wired_and_reads_in_full_without_javascript(): void
    {
        $guide = $this->guide();
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString('data-glab-fuites', $guide);
        $this->assertStringContainsString("document.querySelector('[data-glab-fuites]')", $js);

        // Four accounts to tick, twelve fields on the record, nine reflexes.
        $this->assertSame(4, substr_count($guide, 'data-glab-incident'));
        $this->assertSame(12, substr_count($guide, 'data-glab-field'));
        $this->assertSame(9, substr_count($guide, 'data-glab-for='));

        // Every reflex is in the served page, none hidden before the script
        // has chosen: a reader without JavaScript still gets the whole list.
        preg_match_all('/<li data-glab-for="[^"]*"[^>]*>/', $guide, $cards);
        $this->assertCount(9, $cards[0]);

        foreach ($cards[0] as $card) {
            $this->assertStringNotContainsString('hidden', $card);
        }
    }

    public function test_the_record_never_claims_safety_it_cannot_source(): void
    {
        $guide = $this->guide();

        // A field the sources are silent on reads « Non précisé », never « Non ».
        $this->assertStringContainsString('Non précisé', $guide);
        $this->assertStringContainsString('data-naturabuy="x"', $guide);
        $this->assertStringContainsString('data-note-naturabuy="chiffré"', $guide);
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->get('/guides/proteger-ses-donnees')->assertOk()->getContent();

        $this->assertSame(11, substr_count($html, 'glab-source-num'));
        $this->assertSame(11, substr_count($html, 'rel="noopener nofollow"'));
        $this->assertStringContainsString('cybermalveillance.gouv.fr', $html);
        $this->assertStringContainsString('legifrance.gouv.fr', $html);
        $this->assertStringContainsString('"citation"', $html);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->get('/guides/proteger-ses-donnees')->assertOk()->getContent();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.donnees').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
    }

    public function test_the_hero_carries_its_picture_and_shares_it(): void
    {
        $html = $this->get('/guides/proteger-ses-donnees')->assertOk()->getContent();
        $image = Guides::byRoute('guides.donnees')['image'] ?? null;

        $this->assertNotNull($image);
        $this->assertFileExists(public_path($image));

        // The picture shown whole as a real image, stamped so a replacement
        // reaches returning visitors, described, loaded first, and the same
        // one on a shared link.
        $this->assertStringContainsString('cat-hero cat-hero--plate', $html);
        $this->assertStringContainsString('src="'.versioned_asset($image).'"', $html);
        $this->assertStringContainsString('alt="Illustration : une fiche', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.versioned_asset($image).'">', $html);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.donnees'), array_column(Guides::all(), 'url'));

        $this->get('/guides')->assertOk()
            ->assertSee(route('guides.donnees'), false)
            ->assertSee('Onze pages écrites par la boutique');
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.donnees'), false);
    }
}
