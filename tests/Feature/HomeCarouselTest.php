<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le carrousel qui a remplacé le hero de la page d'accueil.
 *
 * Quatre panneaux, un seul visible. Ce qui compte ici : la page ne tombe
 * jamais, les trois panneaux sont bien dans le HTML servi — un moteur de
 * recherche n'exécute pas forcément le script —, et rien n'est atteignable
 * au clavier tant que le panneau est hors écran.
 */
class HomeCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_still_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_every_panel_is_in_the_served_html(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Le script ne fait que déplacer la piste : les trois panneaux
        // existent avant lui, sinon deux tiers du message seraient invisibles
        // pour un lecteur sans JavaScript.
        $this->assertSame(4, substr_count($html, 'data-carousel-panel'));
    }

    public function test_the_first_panel_is_the_only_one_shown(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // aria-hidden apparaît partout ailleurs (icônes, décor) : il faut
        // compter sur les balises de panneau, pas sur la page entière.
        preg_match_all('/<article[^>]*data-carousel-panel/s', $html, $panels);

        $this->assertCount(4, $panels[0]);
        $this->assertSame(3, substr_count(implode('', $panels[0]), 'aria-hidden="true"'));
    }

    public function test_the_original_hero_copy_survives(): void
    {
        // Le premier panneau est l'ancien hero : le remplacer ne devait rien
        // lui enlever.
        $this->get('/')
            ->assertOk()
            ->assertSee('Équipez-vous', false)
            ->assertSee('pour le stand', false)
            ->assertSee('et le terrain', false)
            ->assertSee(__('store.hero_cta'), false);
    }

    public function test_the_other_panels_point_at_the_right_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(localized_route('products.new-arrivals'), false)
            ->assertSee(localized_route('products.promotions'), false)
            ->assertSee(localized_route('products.best-sellers'), false);
    }

    public function test_the_panels_alternate_sides(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<article\s+class="(home-hero[^"]*)"/', $html, $panels);

        // Texte à gauche, texte à droite, et ainsi de suite : deux panneaux
        // voisins du même côté feraient un glissement sans mouvement apparent.
        $this->assertCount(4, $panels[1]);

        foreach ($panels[1] as $index => $classes) {
            $mirrored = str_contains($classes, 'home-hero--mirrored');

            $this->assertSame(
                $index % 2 === 1,
                $mirrored,
                'le panneau '.($index + 1).' est du mauvais côté'
            );
        }
    }

    public function test_every_panel_keeps_the_hero_styling(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Coque et panneaux partagent la feuille du hero : une classe
        // différente les ferait diverger à la première retouche.
        $this->assertSame(4, substr_count($html, 'home-hero home-carousel-panel'));
        $this->assertSame(4, substr_count($html, '--hero-image'));
    }

    public function test_the_controls_start_hidden(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Sans script les flèches ne feraient rien : un bouton mort vaut
        // moins que pas de bouton.
        foreach (['data-carousel-prev', 'data-carousel-next', 'data-carousel-dots'] as $control) {
            $this->assertMatchesRegularExpression(
                '/'.$control.'[^>]*\shidden>/',
                $html,
                $control.' doit être caché tant que le script ne l\'a pas repris'
            );
        }
    }

    public function test_the_dots_match_the_panel_count(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'data-carousel-dot='));
    }

    public function test_the_carousel_is_announced_as_one(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee(__('store.home_carousel_label'), false);
    }

    public function test_the_heading_id_is_used_once(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // aria-labelledby pointe dessus : trois titres portant le même id
        // rendraient la référence ambiguë.
        $this->assertSame(1, substr_count($html, 'id="home-hero-title"'));
    }

    public function test_an_empty_catalogue_does_not_break_it(): void
    {
        // Les panneaux deux et trois visent des pages de rayon : elles
        // existent même vides, mais le premier dépend de la première
        // catégorie du catalogue.
        $this->assertSame(0, Category::query()->count());

        $this->get('/')->assertOk()->assertSee('data-carousel', false);
    }

    public function test_a_stocked_catalogue_does_not_break_it_either(): void
    {
        Product::factory()->count(3)->create();

        $this->get('/')->assertOk()->assertSee('data-carousel', false);
    }

    public function test_the_script_is_loaded(): void
    {
        $this->get('/')->assertOk()->assertSee('js/home-carousel.js', false);
    }
}
