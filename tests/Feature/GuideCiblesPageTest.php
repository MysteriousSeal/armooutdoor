<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The buying guides: an index linked from the footer, and the Cibles
 * guide it lists - both indexable, both in the pages sitemap.
 */
class GuideCiblesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_guide_page_renders_for_the_end_user(): void
    {
        $this->get('/guides/bien-choisir-sa-cible')->assertOk()
            ->assertSee('Bien choisir')
            ->assertSee('Quatre familles,')
            ->assertSee('Deux réponses,')
            ->assertSee('Questions')
            // One h1, and none of the lab's own vocabulary.
            ->assertDontSee('Essais internes')
            ->assertDontSee('1a')
            // The facts stay the shop's: its one metal target, no invented gongs.
            ->assertSee('réarmement')
            ->assertDontSee('popper')
            ->assertDontSee('AR500');
    }

    public function test_the_guides_index_lists_the_cibles_guide(): void
    {
        $this->get('/guides')->assertOk()
            ->assertSee('Guides')
            ->assertSee('Bien choisir sa cible')
            ->assertSee(route('guides.cibles'));
    }

    public function test_the_footer_links_the_guides_from_every_page(): void
    {
        $this->get('/')->assertOk()->assertSee(route('guides.index'));
    }

    public function test_both_pages_are_published(): void
    {
        $this->get('/guides')->assertOk()->assertDontSee('noindex');
        $this->get('/guides/bien-choisir-sa-cible')->assertOk()->assertDontSee('noindex');

        $this->get('/sitemap-pages.xml')->assertOk()
            ->assertSee(route('guides.index'))
            ->assertSee(route('guides.cibles'));
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

    public function test_the_page_has_exactly_one_h1(): void
    {
        $html = $this->get('/guides/bien-choisir-sa-cible')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
    }
}
