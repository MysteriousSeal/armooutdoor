<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ShippingSetting;
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

    /** The sale panel only exists while something is reduced. */
    private function putOnSale(int $value = 20, string $type = 'percentage'): Product
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);

        Discount::query()->create([
            'product_id' => $product->id,
            'type' => $type,
            'value' => $value,
        ]);

        return $product;
    }

    private function freeShippingOver(int $cents): void
    {
        $carrier = Carrier::query()->create([
            'slug' => 'carousel-carrier',
            'name' => ['en' => 'Carrier', 'fr' => 'Transporteur'],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => 'home',
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ]);

        $setting = ShippingSetting::current();
        $setting->free_shipping_threshold_cents = $cents;
        $setting->free_shipping_carrier_ids = [$carrier->id];
        $setting->save();
    }

    /** @return list<string> */
    private function panels(string $html): array
    {
        preg_match_all('/<article\s+class="home-hero.*?<\/article>/s', $html, $panels);

        return $panels[0];
    }

    public function test_the_homepage_still_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_every_panel_is_in_the_served_html(): void
    {
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        // Le script ne fait que déplacer la piste : les trois panneaux
        // existent avant lui, sinon deux tiers du message seraient invisibles
        // pour un lecteur sans JavaScript.
        $this->assertSame(4, substr_count($html, 'data-carousel-panel'));
    }

    public function test_the_first_panel_is_the_only_one_shown(): void
    {
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        // aria-hidden apparaît partout ailleurs (icônes, décor) : il faut
        // compter sur les balises de panneau, pas sur la page entière.
        preg_match_all('/<article[^>]*data-carousel-panel/s', $html, $panels);

        $this->assertCount(4, $panels[0]);
        $this->assertSame(3, substr_count(implode('', $panels[0]), 'aria-hidden="true"'));
    }

    public function test_the_leading_panel_names_the_aisles(): void
    {
        // Le premier panneau porte le h1 du site : il nomme ce qui se vend
        // vraiment plutôt que de lancer un impératif, parce que c'est le
        // signal de page le plus fort dont dispose la boutique.
        $html = $this->get('/')
            ->assertOk()
            ->assertSee('Cibles de tir,', false)
            ->assertSee('accessoires et camouflage', false)
            ->assertSee('pour le stand et le terrain', false)
            ->assertSee(__('store.hero_cta'), false)
            ->getContent();

        // Airsoft is not a focus of the shop for now: the leading panel
        // leaves it out, whatever the rest of the page lists.
        $leading = $this->panels($html)[0];

        $this->assertStringNotContainsStringIgnoringCase('airsoft', $leading);
        $this->assertStringNotContainsStringIgnoringCase('airgun', $leading);
    }

    public function test_the_other_panels_point_at_the_right_pages(): void
    {
        $this->putOnSale();

        $panels = implode('', $this->panels($this->get('/')->assertOk()->getContent()));

        $this->assertStringContainsString(localized_route('products.new-arrivals'), $panels);
        $this->assertStringContainsString(localized_route('products.promotions'), $panels);
        $this->assertStringContainsString(localized_route('products.best-sellers'), $panels);
    }

    public function test_the_panels_alternate_sides(): void
    {
        $this->putOnSale();
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

    public function test_the_panels_still_alternate_without_the_sale_panel(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<article\s+class="(home-hero[^"]*)"/', $html, $panels);

        $this->assertCount(3, $panels[1]);

        foreach ($panels[1] as $index => $classes) {
            $this->assertSame($index % 2 === 1, str_contains($classes, 'home-hero--mirrored'), 'panneau '.($index + 1));
        }
    }

    public function test_every_panel_keeps_the_hero_styling(): void
    {
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        // Coque et panneaux partagent la feuille du hero : une classe
        // différente les ferait diverger à la première retouche.
        $this->assertSame(4, substr_count($html, 'home-hero home-carousel-panel'));
        $this->assertSame(4, substr_count($html, 'home-hero-photo'));
    }

    public function test_every_panel_has_its_own_image(): void
    {
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/class="home-hero-photo"\s+src="([^"]+)"/', $html, $images);

        // Four panels sharing one photo made the carousel look stuck: the
        // copy changed, the picture behind it did not.
        $this->assertCount(4, $images[1]);
        $this->assertCount(4, array_unique($images[1]));
    }

    public function test_every_panel_names_its_photo(): void
    {
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        // A background-image had no alt text at all; a real <img> is the
        // difference between the photo existing for Google Images and a
        // screen reader, and existing only for a sighted visitor.
        preg_match_all('/class="home-hero-photo"[^>]*\salt="([^"]*)"/', $html, $alts);

        $this->assertCount(4, $alts[1]);

        foreach ($alts[1] as $index => $alt) {
            $this->assertNotSame('', $alt, 'panneau '.($index + 1).' devrait décrire sa photo');
        }
    }

    public function test_only_the_first_photo_loads_eagerly(): void
    {
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all(
            '/<img\s+class="home-hero-photo"[^>]*>/',
            $html,
            $photos
        );

        $this->assertCount(4, $photos[0]);

        // The leading panel is the LCP candidate and is visible without
        // scripting; the three behind it are off-screen until the carousel
        // script runs, so their weight can wait.
        foreach ($photos[0] as $index => $tag) {
            $this->assertSame($index === 0, str_contains($tag, 'fetchpriority="high"'), 'panneau '.($index + 1));
            $this->assertSame($index === 0, str_contains($tag, 'loading="eager"'), 'panneau '.($index + 1));
            $this->assertSame($index !== 0, str_contains($tag, 'loading="lazy"'), 'panneau '.($index + 1));
        }
    }

    public function test_every_panel_names_where_its_subject_sits(): void
    {
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/--hero-focus:\s*([0-9]+)%/', $html, $focus);

        // Stacked, the panel is portrait and `cover` cuts most of the width
        // away. Without a focal column the crop keeps the empty half.
        $this->assertCount(4, $focus[1]);

        foreach ($focus[1] as $index => $percent) {
            $this->assertGreaterThan(0, (int) $percent, 'panneau '.($index + 1));
            $this->assertLessThan(100, (int) $percent, 'panneau '.($index + 1));
        }
    }

    public function test_the_sale_panel_is_left_out_when_nothing_is_reduced(): void
    {
        // A promotions panel over an empty promotions page is a dead end.
        $panels = implode('', $this->panels($this->get('/')->assertOk()->getContent()));

        $this->assertStringNotContainsString('images/hero-3.webp', $panels);
        $this->assertStringNotContainsString(localized_route('products.promotions'), $panels);
    }

    public function test_with_nothing_reduced_the_third_panel_offers_free_shipping(): void
    {
        $this->freeShippingOver(6000);

        $panels = $this->panels($this->get('/')->assertOk()->getContent());

        // Still four panels: the offer the shop does have takes the slot
        // rather than the carousel losing one.
        $this->assertCount(4, $panels);
        $this->assertStringContainsString('Livraison offerte', $panels[2]);
        $this->assertStringContainsString('dès 60€', $panels[2]);
        $this->assertStringContainsString('images/hero-3.webp', $panels[2]);
        $this->assertStringNotContainsString(localized_route('products.promotions'), implode('', $panels));
        // Named carriers rather than "à domicile ou en point relais": the
        // offer only covers some of them, and a vaguer line would promise the
        // rest.
        $this->assertStringContainsString('Avec Transporteur, partout en France métropolitaine.', $panels[2]);
    }

    public function test_a_real_discount_takes_the_third_panel_over_free_shipping(): void
    {
        $this->freeShippingOver(6000);
        $this->putOnSale(20);

        $panels = $this->panels($this->get('/')->assertOk()->getContent());

        $this->assertCount(4, $panels);
        $this->assertStringContainsString('Jusqu’à -20%', $panels[2]);
        $this->assertStringNotContainsString('Livraison offerte', $panels[2]);
    }

    public function test_the_sale_panel_names_the_deepest_percentage(): void
    {
        $this->putOnSale(10);
        $this->putOnSale(29);

        $this->get('/')
            ->assertOk()
            ->assertSee('Jusqu’à -29%')
            ->assertDontSee('cette semaine');
    }

    public function test_an_expired_discount_does_not_set_the_headline_figure(): void
    {
        $this->putOnSale(15);
        $expired = Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        Discount::query()->create([
            'product_id' => $expired->id,
            'type' => 'percentage',
            'value' => 60,
            'ends_at' => now()->subDay(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Jusqu’à -15%')
            ->assertDontSee('Jusqu’à -60%');
    }

    public function test_fixed_amount_discounts_alone_claim_no_percentage(): void
    {
        $this->putOnSale(500, 'fixed');

        $this->get('/')
            ->assertOk()
            ->assertSee('Des prix', false)
            ->assertSee('réduits', false)
            ->assertDontSee('Jusqu’à');
    }

    public function test_the_leading_panel_links_the_categories_that_exist(): void
    {
        Category::factory()->create(['slug' => 'vetements', 'name' => ['fr' => 'Vêtements et accessoires', 'en' => 'Clothing']]);
        Category::factory()->create(['slug' => 'accessoires-de-l-arme', 'name' => ['fr' => 'Accessoires de l’arme', 'en' => 'Gun accessories']]);
        Category::factory()->create(['slug' => 'repliques-airsoft', 'name' => ['fr' => 'Répliques airsoft', 'en' => 'Airsoft']]);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<ul class="home-hero-tags".*?<\/ul>/s', $html, $tags);
        preg_match_all('/<a href="([^"]+)">([^<]+)<\/a>/', $tags[0] ?? '', $links);

        // In the hero's order, not the database's. Targets have no category
        // here, so they are skipped rather than linked to a 404, and airsoft
        // is not one of the hero's aisles at all.
        $this->assertSame([
            localized_route('categories.show', ['category' => 'accessoires-de-l-arme']),
            localized_route('categories.show', ['category' => 'vetements']),
        ], $links[1]);
        // The chips keep short names of their own: beside « Accessoires »,
        // « Vêtements et accessoires » would say accessories twice.
        $this->assertSame(['Accessoires', 'Vêtements'], $links[2]);
    }

    public function test_the_leading_panel_offers_every_category_instead_of_repeating_new_arrivals(): void
    {
        $panels = $this->panels($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString(localized_route('categories.index'), $panels[0]);
        $this->assertStringNotContainsString(localized_route('products.new-arrivals'), $panels[0]);
    }

    public function test_the_new_arrivals_panel_counts_the_last_thirty_days(): void
    {
        Product::factory()->count(10)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => true, 'created_at' => now()->subDays(40)]);

        $this->get('/')
            ->assertOk()
            ->assertSee('10 articles ajoutés au catalogue ces 30 derniers jours.');
    }

    public function test_the_new_arrivals_panel_hides_a_figure_under_ten(): void
    {
        // "9 articles in 30 days" advertises a quiet shop: below ten the
        // panel describes the page instead of counting.
        Product::factory()->count(9)->create(['is_active' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee(__('store.home_slide_new_text_plain'))
            ->assertDontSee('ces 30 derniers jours');
    }

    public function test_the_new_arrivals_panel_invents_no_figure_when_nothing_is_recent(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('store.home_slide_new_text_plain'))
            ->assertDontSee('ces 30 derniers jours');
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
        $this->putOnSale();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'data-carousel-dot='));
    }

    public function test_the_dots_follow_the_panels_when_the_sale_panel_is_gone(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-carousel-panel'));
        $this->assertSame(3, substr_count($html, 'data-carousel-dot='));
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
