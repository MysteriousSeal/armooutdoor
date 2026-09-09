<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The guides: an index linked from the footer, and every guide it lists.
 * All indexable, all in the guides sitemap.
 */
class GuideCiblesPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function guidePages(): array
    {
        return [
            'cibles' => ['/guides/bien-choisir-sa-cible', 'Bien choisir sa cible', 'Guide'],
            'entretien' => ['/guides/entretenir-son-arme', 'Entretenir son arme', 'Guide'],
            'classification' => ['/guides/classer-son-arme', 'Classer son arme', 'Réglementation'],
            'joules' => ['/guides/joules-et-fps', 'Joules et FPS', 'Énergie'],
            'glossaire' => ['/guides/glossaire', 'Le glossaire', 'Vocabulaire'],
            'ou-tirer' => ['/guides/ou-tirer-legalement', 'Où tirer légalement', 'Lieux'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function guideUrls(): array
    {
        return array_map(fn (array $row): array => [$row[0]], self::guidePages());
    }

    public function test_the_guide_page_renders_for_the_end_user(): void
    {
        $html = $this->get('/guides/bien-choisir-sa-cible')->assertOk()->getContent();

        // Read the guide itself, not the page around it. « 1a » was matched
        // against the whole document, and the CSRF token in the head is
        // forty random letters and digits: it carries « 1a » about one run
        // in a hundred, which is a test that fails for no reason.
        preg_match('/<main.*?<\/main>/s', $html, $main);
        $guide = $main[0] ?? '';

        foreach (['Bien choisir', 'Quatre familles,', 'Deux réponses,', 'Questions', 'réarmement'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }

        // None of the lab's own vocabulary, and the facts stay the shop's:
        // its one metal target, no invented gongs.
        foreach (['Essais internes', 'Cible 1a', 'popper', 'AR500'] as $unwanted) {
            $this->assertStringNotContainsString($unwanted, $guide);
        }
    }

    public function test_the_guides_index_lists_the_cibles_guide(): void
    {
        $this->get('/guides')->assertOk()
            ->assertSee('Guides')
            ->assertSee('Bien choisir sa cible')
            ->assertSee(route('guides.cibles'));
    }

    /**
     * The two reading links used to have a column of their own, headed
     * "Conseils". They sit under Aide & infos now, since two links left the
     * grid ragged and both errands are the same one. What matters here is
     * that the footer still reaches them.
     */
    public function test_the_footer_links_guides_and_blog(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Les guides')
            ->assertSee('Le blog')
            ->assertSee(route('guides.index'))
            ->assertSee(route('blog.index'));
    }

    public function test_both_pages_are_published(): void
    {
        $this->get('/guides')->assertOk()->assertDontSee('noindex');
        $this->get('/guides/bien-choisir-sa-cible')->assertOk()->assertDontSee('noindex');

        $this->get('/sitemap-guides.xml')->assertOk()
            ->assertSee(route('guides.index'))
            ->assertSee(route('guides.cibles'));
        $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-guides.xml', false);
        $this->get('/sitemap-pages.xml')->assertOk()->assertDontSee(route('guides.index'));
    }

    public function test_both_pages_declare_their_structured_data(): void
    {
        $this->get('/guides')->assertOk()
            ->assertSee('CollectionPage')
            ->assertSee('BreadcrumbList');

        // The default recommendation is server-rendered: crawlable links,
        // and a real block without JavaScript.
        $this->get('/guides/bien-choisir-sa-cible')->assertOk()
            ->assertSee('"Article"', false)
            ->assertSee('FAQPage')
            ->assertSee('BreadcrumbList')
            ->assertSee(route('categories.show', 'cibles-rondes'))
            ->assertSee(route('categories.show', 'cibles-carrees'))
            ->assertSee(route('categories.show', 'cibles-carton-metal'));
    }

    public function test_the_entretien_guide_renders_published_and_declared(): void
    {
        $this->get('/guides/entretenir-son-arme')->assertOk()
            ->assertSee('Entretenir')
            ->assertSee('couronnement')
            ->assertSee('FAQPage')
            ->assertSee('BreadcrumbList')
            ->assertDontSee('noindex')
            // The default recommendation is server-rendered and crawlable.
            ->assertSee(route('products.show', 'corde-nettoyage-canon-22-223-5-56mm-bore-rope'))
            ->assertSee(route('categories.show', 'entretien-arme'));

        $this->get('/guides')->assertOk()->assertSee(route('guides.entretien'));
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.entretien'));

        $html = $this->get('/guides/entretenir-son-arme')->getContent();
        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
    }

    public function test_the_classification_guide_renders_published_and_declared(): void
    {
        $this->get('/guides/classer-son-arme')->assertOk()
            ->assertSee('Classer')
            ->assertSee('20 joules')
            ->assertSee('FAQPage')
            ->assertSee('BreadcrumbList')
            ->assertDontSee('noindex')
            ->assertSee(route('blog.show', 'categorie-d-ce-que-la-loi-francaise-range-vraiment-dedans-et-ce-que-ca-change-pour-vous'))
            ->assertSee(route('blog.show', 'categorie-c-les-armes-soumises-a-declaration-et-tout-ce-qui-va-avec'))
            ->assertSee(route('blog.show', 'categorie-b-les-armes-soumises-a-autorisation-et-comment-on-y-entre'))
            ->assertSee(route('blog.show', 'categorie-a-ce-qui-est-interdit-a-qui-et-comment-une-arme-b-y-bascule-dun-chargeur'))
            ->assertSee(route('categories.show', 'repliques-airsoft'));

        $this->get('/guides')->assertOk()
            ->assertSee('Classer son arme')
            ->assertSee(route('guides.classification'));
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.classification'));

        $html = $this->get('/guides/classer-son-arme')->getContent();
        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
    }

    public function test_the_page_has_exactly_one_h1(): void
    {
        $html = $this->get('/guides/bien-choisir-sa-cible')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
    }

    #[DataProvider('guidePages')]
    public function test_each_guide_leads_with_the_shop_hero(string $url, string $title, string $kicker): void
    {
        $html = $this->get($url)->assertOk()
            ->assertSee('cat-hero', false)
            ->assertSee('css/categories.css', false)
            ->assertSee('css/guides.css', false)
            ->assertSee('<p class="cat-hero-kicker">'.$kicker.'</p>', false)
            ->getContent();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
        $this->assertMatchesRegularExpression(
            '/<h1 class="cat-hero-title[^"]*">\s*<span class="cat-hero-title-accent">'.preg_quote($title, '/').'/',
            $html,
        );
        $this->assertStringNotContainsString('cat-hero has-image', $html);
    }

    #[DataProvider('guideUrls')]
    public function test_each_guide_names_the_section_guides(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString(
            'href="'.route('guides.index').'">Guides</a>',
            $html,
        );
        $this->assertStringContainsString('"name":"Guides"', $html);
        $this->assertStringNotContainsString("Guides d'achat", $html);
        $this->assertStringNotContainsString('Guides d&#039;achat', $html);
        $this->assertStringNotContainsString("Guide d'achat", $html);
        $this->assertStringNotContainsString('Guide d&#039;achat', $html);
    }

    public function test_the_guides_index_is_headed_guides(): void
    {
        $html = $this->get('/guides')->assertOk()
            ->assertSee('<title>Guides — Armo Outdoor</title>', false)
            ->assertSee('<span class="glab-title-accent">Guides</span>', false)
            ->assertSee('"@type":"CollectionPage"', false)
            ->getContent();

        $this->assertStringContainsString('"name":"Guides"', $html);
        $this->assertStringNotContainsString("Guides d'achat", $html);
        $this->assertStringNotContainsString('Guides d&#039;achat', $html);
    }

    public function test_the_classification_guide_lays_out_the_four_categories_as_chapters(): void
    {
        $html = $this->get('/guides/classer-son-arme')->assertOk()
            ->assertSee('glab-chapters', false)
            ->assertSee('glab-chapter', false)
            ->assertSee('glab-chapter-more', false)
            ->assertSee(route('blog.show', 'categorie-b-les-armes-soumises-a-autorisation-et-comment-on-y-entre'))
            ->getContent();

        preg_match('#<ul class="cat-hero-tags">(.*?)</ul>#s', $html, $tags);
        $this->assertNotEmpty($tags, 'The classification hero lists D, C, B and A.');
        foreach (['D', 'C', 'B', 'A'] as $tag) {
            $this->assertStringContainsString('<li>'.$tag.'</li>', $tags[0]);
        }

        $this->assertSame(5, substr_count($html, 'class="glab-chapter"'));
    }

    /**
     * One sheet and one script for the whole shelf.
     *
     * There were eight of each: eight requests, and eight places to look for
     * a rule, for a total no single page used more than a fifth of. They are
     * fenced into named sections inside one file now, and every guide asks
     * for that file and nothing else. A ninth file appearing beside them is
     * what this catches.
     */
    public function test_the_shelf_is_served_from_one_sheet_and_one_script(): void
    {
        $this->assertFileExists(public_path('css/guides.css'));
        $this->assertFileExists(public_path('js/guides.js'));

        // And no folder of per-guide files grows back beside them.
        $this->assertSame([], glob(public_path('css/guides/*')));
        $this->assertSame([], glob(public_path('js/guides/*')));

        $urls = array_merge([route('guides.index')], array_column(Guides::all(), 'url'));

        foreach ($urls as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'css/guides.css'), $url.' asks for more than one sheet');
            $this->assertLessThanOrEqual(1, substr_count($html, 'js/guides.js'), $url.' asks for more than one script');
        }
    }

    /**
     * Four selectors, one engine.
     *
     * Three guides name a handful of products and the camouflage guide ranks
     * seven motifs, but the chips, the state, the pressed class and the
     * rendering are the same work. Each guide supplies only what it knows:
     * a function turning two answers into a sentence and a list of cards.
     */
    public function test_every_selector_runs_on_the_shared_engine(): void
    {
        $js = file_get_contents(public_path('js/guides.js'));

        $this->assertStringContainsString('var GlabSelector = (function () {', $js);

        foreach (['classification', 'cibles', 'entretien', 'camouflage'] as $guide) {
            $this->assertStringContainsString(
                'GlabSelector.mount(\'[data-glab-selector="'.$guide.'"]\'',
                $js,
                $guide.' does not use the shared engine',
            );
        }

        // The card is built once. Four copies of this were what the engine
        // replaced, and a second would mean a guide has gone back to doing
        // its own rendering.
        $this->assertSame(1, substr_count($js, "'glab-reco-rank'"));
        $this->assertSame(1, substr_count($js, "'glab-reco-head'"));
    }

    /**
     * Each selector says which guide it belongs to.
     *
     * Three guides run a two-answer selector, and while they had a script
     * each in a file each, every one of them could look for a nameless
     * attribute and find its own. Once the six scripts became one file they
     * all ran on every guide, every one of them found the same nameless
     * root, and the last to run painted its own recommendations over the
     * others: the targets guide answered with cleaning rods.
     */
    public function test_a_selector_is_named_for_the_guide_it_belongs_to(): void
    {
        $js = file_get_contents(public_path('js/guides.js'));

        // Nothing mounts on an unqualified attribute any more.
        $this->assertStringNotContainsString("'[data-glab-selector]'", $js);

        foreach (['classification' => 'classer-son-arme',
            'cibles' => 'bien-choisir-sa-cible',
            'entretien' => 'entretenir-son-arme'] as $name => $slug) {
            $this->assertStringContainsString('[data-glab-selector="'.$name.'"]', $js, $name.' has no named mount');

            $this->assertStringContainsString(
                'data-glab-selector="'.$name.'"',
                $this->get('/guides/'.$slug)->assertOk()->getContent(),
                $slug.' does not name its selector',
            );
        }
    }

    public function test_a_guide_hero_without_a_photograph_does_not_keep_the_empty_height(): void
    {
        $css = file_get_contents(public_path('css/guides.css'));

        $this->assertStringContainsString('.glab .cat-hero:not(.has-image)', $css);
        $this->assertStringContainsString('min-height: 0', $css);
    }
}
