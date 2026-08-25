<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace;
use App\Models\NaturabuyListing;
use App\Models\Product;
use App\Models\User;
use App\Services\Naturabuy\NaturabuyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La place de marché NaturaBuy dans l'administration.
 *
 * Rien ici ne touche leur API : tout passe par `Http::fake()`. Un test qui
 * appellerait le vrai service dépendrait du réseau, du jeton et de leur
 * disponibilité, et pourrait à terme écrire chez eux.
 */
class NaturabuyListingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function listing(array $overrides = []): NaturabuyListing
    {
        return NaturabuyListing::query()->create(array_merge([
            'naturabuy_id' => fake()->unique()->numberBetween(1, 999999),
            'title' => 'Ceinture Tactique EVA Khaki',
            'url' => 'ceinture-item-1.html',
            'internalcode' => 'MOLLE-BELT-EVA-KHAKI',
            'price_cents' => 2299,
            'quantity' => 3,
            'out_of_stock' => false,
            'closed' => false,
            'synced_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------- admin

    public function test_the_marketplace_index_lists_naturabuy(): void
    {
        Marketplace::query()->create(['name' => 'NaturaBuy']);
        $this->listing();

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces')
            ->assertOk()
            ->assertSee('NaturaBuy')
            ->assertSee(route('admin.marketplaces.naturabuy'), false);
    }

    public function test_the_listings_page_shows_a_listing(): void
    {
        $listing = $this->listing();

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee($listing->title)
            ->assertSee('MOLLE-BELT-EVA-KHAKI')
            ->assertSee('22,99', false);
    }

    public function test_the_tabs_filter_by_stock(): void
    {
        $inStock = $this->listing(['title' => 'En stock', 'out_of_stock' => false]);
        $empty = $this->listing(['title' => 'Rupture', 'out_of_stock' => true]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?tab=in-stock')
            ->assertOk()->assertSee($inStock->title)->assertDontSee($empty->title);

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?tab=out-of-stock')
            ->assertOk()->assertSee($empty->title)->assertDontSee($inStock->title);
    }

    /**
     * Une annonce close est terminée : elle ne se montre nulle part, quel que
     * soit l'onglet, et ne compte dans aucun total.
     */
    public function test_a_closed_listing_is_never_displayed(): void
    {
        $open = $this->listing(['title' => 'Toujours en vente', 'closed' => false, 'internalcode' => 'OPEN-CODE']);
        $closed = $this->listing(['title' => 'Annonce terminee', 'closed' => true, 'internalcode' => 'CLOSED-CODE']);

        $admin = $this->admin();

        foreach (['', '?tab=in-stock', '?tab=out-of-stock'] as $query) {
            $this->actingAs($admin)->get('/admin/marketplaces/naturabuy'.$query)
                ->assertOk()
                ->assertDontSee($closed->title);
        }

        // Même recherchée par son titre exact, elle ne remonte pas. On vérifie
        // sur le code interne : le terme cherché, lui, est réaffiché dans le
        // champ de recherche et ne prouverait rien.
        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?search=Annonce+terminee')
            ->assertOk()
            ->assertDontSee('CLOSED-CODE');

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee($open->title);
    }

    /** Les compteurs suivent la table : sinon les chiffres se contrediraient. */
    public function test_the_counts_leave_closed_listings_out(): void
    {
        $this->listing(['closed' => false, 'out_of_stock' => false]);
        $this->listing(['closed' => true, 'out_of_stock' => false]);
        $this->listing(['closed' => true, 'out_of_stock' => true]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('1 on sale')
            ->assertSee('1 in stock')
            ->assertSee('0 out of stock');
    }

    public function test_the_marketplace_card_counts_only_open_listings(): void
    {
        Marketplace::query()->create(['name' => 'NaturaBuy']);
        $this->listing(['closed' => false]);
        $this->listing(['closed' => true]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces')
            ->assertOk()
            ->assertSee('>1<', false);
    }

    public function test_the_search_matches_title_and_internal_code(): void
    {
        $wanted = $this->listing(['title' => 'Cagoule Urban', 'internalcode' => 'CAG-URBBLK-BREATH']);
        $other = $this->listing(['title' => 'Ruban camo', 'internalcode' => 'TAPE-CAMO-JGL']);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?search=CAG-URBBLK')
            ->assertOk()->assertSee($wanted->title)->assertDontSee($other->title);

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?search=Cagoule')
            ->assertOk()->assertSee($wanted->title)->assertDontSee($other->title);
    }

    public function test_the_empty_state_points_at_the_sync_command(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('naturabuy:sync');
    }

    public function test_a_customer_cannot_reach_the_marketplace_pages(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin/marketplaces')->assertRedirect();
        $this->actingAs($customer)->get('/admin/marketplaces/naturabuy')->assertRedirect();
    }

    // ----------------------------------------------------------- catalogue

    public function test_a_listing_matching_a_product_links_to_it(): void
    {
        $product = Product::factory()->create(['sku' => 'MOLLE-BELT-EVA-KHAKI']);
        $this->listing(['internalcode' => 'MOLLE-BELT-EVA-KHAKI']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('In catalogue')
            ->assertSee(route('admin.products.edit', $product->id), false);
    }

    /** Une déclinaison renvoie vers son produit parent, qui a la fiche. */
    public function test_a_listing_matching_a_variant_links_to_the_parent(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'L']],
            'sku' => 'MFL-72-010',
            'quantity' => 0,
        ]);
        $this->listing(['internalcode' => 'MFL-72-010']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('In catalogue')
            ->assertSee(route('admin.products.edit', $product->id), false);
    }

    /** Code renseigné mais introuvable : un écart, pas une donnée absente. */
    public function test_an_unmatched_code_reads_as_not_in_catalogue(): void
    {
        $this->listing(['internalcode' => '000019-1']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('admin-availability-chip is-out-of-stock', false)
            ->assertDontSee('admin-availability-chip is-in-stock', false);
    }

    public function test_a_listing_without_a_code_reads_as_no_code(): void
    {
        $this->listing(['internalcode' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('No code')
            // Sur la pastille, pas sur le texte : « Not in catalogue » est
            // aussi le libellé d'un onglet, présent à chaque chargement.
            ->assertDontSee('admin-availability-chip is-out-of-stock', false);
    }

    /** Le rapprochement tient en deux requêtes, quel que soit le nombre de lignes. */
    public function test_matching_does_not_query_per_row(): void
    {
        foreach (range(1, 12) as $i) {
            Product::factory()->create(['sku' => 'SKU-'.$i]);
            $this->listing(['internalcode' => 'SKU-'.$i]);
        }

        \DB::enableQueryLog();
        $this->actingAs($this->admin())->get('/admin/marketplaces/naturabuy')->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "Expected a handful of queries, ran {$queries}");
    }

    public function test_the_catalogue_tabs_split_the_listings(): void
    {
        Product::factory()->create(['sku' => 'KNOWN-SKU']);
        $matched = $this->listing(['title' => 'Connue', 'internalcode' => 'KNOWN-SKU']);
        $unmatched = $this->listing(['title' => 'Inconnue', 'internalcode' => '000019-1']);
        $noCode = $this->listing(['title' => 'Sans code', 'internalcode' => null]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?tab=in-catalogue')
            ->assertOk()
            ->assertSee($matched->title)
            ->assertDontSee($unmatched->title)
            ->assertDontSee($noCode->title);

        // Non rapproché veut dire « pas de produit ici », code absent compris.
        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?tab=not-in-catalogue')
            ->assertOk()
            ->assertSee($unmatched->title)
            ->assertSee($noCode->title)
            ->assertDontSee($matched->title);
    }

    /** Les deux onglets se partagent tout : leur somme fait le total. */
    public function test_the_two_catalogue_tabs_add_up_to_all(): void
    {
        Product::factory()->create(['sku' => 'KNOWN-SKU']);
        $this->listing(['internalcode' => 'KNOWN-SKU']);
        $this->listing(['internalcode' => 'NOPE']);
        $this->listing(['internalcode' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('In catalogue <span class="admin-tab-count">1</span>', false)
            ->assertSee('Not in catalogue <span class="admin-tab-count">2</span>', false);
    }

    public function test_a_quantity_gap_is_flagged(): void
    {
        Product::factory()->create(['sku' => 'GAP-SKU', 'quantity' => 7]);
        $this->listing(['internalcode' => 'GAP-SKU', 'quantity' => 2]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('is-differing', false)
            ->assertSee('+5');
    }

    public function test_equal_quantities_are_not_flagged(): void
    {
        Product::factory()->create(['sku' => 'SAME-SKU', 'quantity' => 3]);
        $this->listing(['internalcode' => 'SAME-SKU', 'quantity' => 3]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertDontSee('is-differing', false);
    }

    /** Une déclinaison se compare à sa propre quantité, pas au total du parent. */
    public function test_a_variant_compares_its_own_quantity(): void
    {
        $product = Product::factory()->create(['sku' => null, 'quantity' => 40]);
        $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'L']],
            'sku' => 'VAR-L',
            'quantity' => 4,
        ]);
        $this->listing(['internalcode' => 'VAR-L', 'quantity' => 4]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            // 4 contre 4 : aucun écart. Comparer aux 40 du parent en inventerait un.
            ->assertDontSee('is-differing', false);
    }

    /**
     * Le cas réel : NaturaBuy vend une annonce par coloris, la taille étant
     * choisie à l'achat ; ici chaque taille est une déclinaison suffixée, et
     * le produit parent n'a aucun SKU puisqu'il a des déclinaisons.
     */
    public function test_a_listing_matches_variant_skus_by_prefix(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        foreach (['S' => 0, 'M' => 1, 'L' => 0] as $size => $qty) {
            $product->variants()->create([
                'attribute_values' => [['label' => 'Taille', 'value' => $size]],
                'sku' => 'M-SPORT-TEE-CAMOWHT-POLY-'.$size,
                'quantity' => $qty,
            ]);
        }

        $this->listing(['internalcode' => 'M-SPORT-TEE-CAMOWHT-POLY', 'quantity' => 1]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('By prefix')
            ->assertSee(route('admin.products.edit', $product->id), false)
            // La quantité comparée est la somme des déclinaisons : 0+1+0 = 1.
            ->assertDontSee('is-differing', false);
    }

    /** Un produit sans déclinaison peut aussi porter un SKU suffixé. */
    public function test_a_listing_matches_a_product_sku_by_prefix(): void
    {
        $product = Product::factory()->create(['sku' => 'REVASRI-NK600-BLK', 'quantity' => 2]);
        $this->listing(['internalcode' => 'REVASRI-NK600', 'quantity' => 2]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('By prefix')
            ->assertSee(route('admin.products.edit', $product->id), false);
    }

    /** Exact d'abord : un code qui tombe juste ne passe pas par le repli. */
    public function test_an_exact_match_wins_over_a_prefix_one(): void
    {
        Product::factory()->create(['sku' => 'TEE-BLK', 'quantity' => 5]);
        Product::factory()->create(['sku' => 'TEE-BLK-XL', 'quantity' => 99]);
        $this->listing(['internalcode' => 'TEE-BLK', 'quantity' => 5]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('In catalogue')
            ->assertDontSee('By prefix');
    }

    /** Le tiret est exigé : sans lui, « ABC » réclamerait « ABCD ». */
    public function test_a_prefix_without_a_separator_does_not_match(): void
    {
        Product::factory()->create(['sku' => 'ABCD', 'quantity' => 1]);
        $this->listing(['internalcode' => 'ABC']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('admin-availability-chip is-out-of-stock', false)
            ->assertDontSee('By prefix');
    }

    public function test_a_prefix_match_counts_as_in_catalogue_in_the_tabs(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'PREF-CODE-M',
            'quantity' => 1,
        ]);
        $matched = $this->listing(['title' => 'Par prefixe', 'internalcode' => 'PREF-CODE']);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?tab=in-catalogue')
            ->assertOk()->assertSee($matched->title);

        $this->actingAs($admin)->get('/admin/marketplaces/naturabuy?tab=not-in-catalogue')
            ->assertOk()->assertDontSee($matched->title);
    }

    // --------------------------------------------------------- second name

    public function test_our_name_is_shown_under_theirs_on_the_catalogue_tab(): void
    {
        $product = Product::factory()->create(['sku' => 'NAME-SKU', 'name' => ['fr' => 'Notre nom a nous']]);
        $this->listing(['title' => 'Leur titre', 'internalcode' => 'NAME-SKU']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=in-catalogue')
            ->assertOk()
            ->assertSee('Leur titre')
            ->assertSee('Notre nom a nous')
            ->assertSee(route('admin.products.edit', $product->id), false)
            ->assertSee('differs');
    }

    public function test_identical_names_carry_no_marker(): void
    {
        Product::factory()->create(['sku' => 'SAME-NAME', 'name' => ['fr' => 'Un meme titre']]);
        $this->listing(['title' => 'Un meme titre', 'internalcode' => 'SAME-NAME']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=in-catalogue')
            ->assertOk()
            ->assertSee('nb-ourname', false)
            ->assertDontSee('nb-ourname-tag', false);
    }

    /** Le second nom n'apparaît que sur cet onglet. */
    public function test_the_second_name_is_absent_from_the_other_tabs(): void
    {
        Product::factory()->create(['sku' => 'ELSEWHERE', 'name' => ['fr' => 'Notre nom a nous']]);
        $this->listing(['title' => 'Leur titre', 'internalcode' => 'ELSEWHERE']);

        $admin = $this->admin();

        foreach (['', '?tab=in-stock', '?tab=not-in-catalogue', '?tab=qty-mismatch'] as $query) {
            $this->actingAs($admin)->get('/admin/marketplaces/naturabuy'.$query)
                ->assertOk()
                ->assertDontSee('nb-ourname', false);
        }
    }

    /** Un rapprochement par préfixe montre le nom du produit parent. */
    public function test_a_prefix_match_shows_the_parent_name(): void
    {
        $product = Product::factory()->create(['sku' => null, 'name' => ['fr' => 'Le parent']]);
        $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'PARENT-CODE-M',
            'quantity' => 1,
        ]);
        $this->listing(['title' => 'Leur titre', 'internalcode' => 'PARENT-CODE']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=in-catalogue')
            ->assertOk()
            ->assertSee('Le parent');
    }

    // ------------------------------------------------------- name mismatch

    public function test_the_name_mismatch_tab_holds_only_differing_names(): void
    {
        Product::factory()->create(['sku' => 'SAME-N', 'name' => ['fr' => 'Titre commun']]);
        Product::factory()->create(['sku' => 'DIFF-N', 'name' => ['fr' => 'Notre titre']]);

        $same = $this->listing(['title' => 'Titre commun', 'internalcode' => 'SAME-N']);
        $diff = $this->listing(['title' => 'Leur titre', 'internalcode' => 'DIFF-N']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=name-mismatch')
            ->assertOk()
            ->assertSee($diff->title)
            ->assertDontSee($same->title);
    }

    /** Sans correspondance, il n'y a pas de nom à comparer. */
    public function test_an_unmatched_listing_is_not_a_name_mismatch(): void
    {
        $orphan = $this->listing(['title' => 'Orpheline', 'internalcode' => 'NOTHING-AT-ALL']);
        $noCode = $this->listing(['title' => 'Sans code', 'internalcode' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=name-mismatch')
            ->assertOk()
            ->assertDontSee($orphan->title)
            ->assertDontSee($noCode->title);
    }

    /** Une déclinaison se compare au nom de son parent. */
    public function test_a_variant_match_compares_the_parent_name(): void
    {
        $product = Product::factory()->create(['sku' => null, 'name' => ['fr' => 'Nom du parent']]);
        $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'VAR-NAME-M',
            'quantity' => 1,
        ]);

        $same = $this->listing(['title' => 'Nom du parent', 'internalcode' => 'VAR-NAME-M']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=name-mismatch')
            ->assertOk()
            ->assertDontSee($same->title);
    }

    /** Un rapprochement par préfixe compte, comme pour les quantités. */
    public function test_a_prefix_match_can_be_a_name_mismatch(): void
    {
        $product = Product::factory()->create(['sku' => null, 'name' => ['fr' => 'Notre libelle']]);
        $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'PFXN-CODE-M',
            'quantity' => 1,
        ]);

        $gap = $this->listing(['title' => 'Leur libelle', 'internalcode' => 'PFXN-CODE']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=name-mismatch')
            ->assertOk()
            ->assertSee($gap->title);
    }

    /** L'onglet montre les deux noms : sans eux il ne dirait rien d'utile. */
    public function test_the_name_mismatch_tab_shows_both_names(): void
    {
        Product::factory()->create(['sku' => 'BOTH-N', 'name' => ['fr' => 'Notre appellation']]);
        $this->listing(['title' => 'Leur appellation', 'internalcode' => 'BOTH-N']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=name-mismatch')
            ->assertOk()
            ->assertSee('Leur appellation')
            ->assertSee('Notre appellation')
            ->assertSee('nb-ourname', false);
    }

    public function test_the_name_mismatch_count_matches_the_tab(): void
    {
        Product::factory()->create(['sku' => 'N1', 'name' => ['fr' => 'Pareil']]);
        Product::factory()->create(['sku' => 'N2', 'name' => ['fr' => 'Different']]);
        $this->listing(['title' => 'Pareil', 'internalcode' => 'N1']);
        $this->listing(['title' => 'Autre chose', 'internalcode' => 'N2']);
        $this->listing(['internalcode' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('Name mismatch <span class="admin-tab-count">1</span>', false);
    }

    /** Les onglets d'alerte s'allument dès qu'il y a quelque chose. */
    public function test_the_attention_tabs_light_up_when_they_have_something(): void
    {
        Product::factory()->create(['sku' => 'Q-GAP', 'quantity' => 9, 'name' => ['fr' => 'Autre nom']]);
        // En rupture chez eux, en écart de nom et de quantité : les trois.
        $this->listing(['title' => 'Leur titre', 'internalcode' => 'Q-GAP', 'quantity' => 1, 'out_of_stock' => true]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'nb-tab-attention'));
    }

    public function test_out_of_stock_turns_red_only_when_there_are_any(): void
    {
        $this->listing(['out_of_stock' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertDontSee('nb-tab-attention', false);

        $this->listing(['out_of_stock' => true]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('nb-tab-attention', false);
    }

    /**
     * Trois familles dans la barre : le rapprochement, les écarts, le stock.
     * Chacune s'ouvre par un filet, et rien n'est renvoyé au bout.
     */
    public function test_the_tab_bar_is_grouped_in_three(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')->assertOk()->getContent();

        $nav = substr($html, strpos($html, 'admin-tabs'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));

        $this->assertSame(3, substr_count($nav, 'starts-group'));
        // « Out of stock » revient auprès de « In stock » : plus rien au bout.
        $this->assertStringNotContainsString('sits-apart', $nav);
        $this->assertLessThan(
            strpos($nav, 'Out of stock'),
            strpos($nav, 'In stock'),
            'In stock comes before Out of stock',
        );
    }

    /** À zéro, aucun rouge : un avertissement permanent ne dit plus rien. */
    public function test_a_mismatch_tab_stays_neutral_at_zero(): void
    {
        Product::factory()->create(['sku' => 'ALL-OK', 'quantity' => 3, 'name' => ['fr' => 'Meme titre']]);
        $this->listing(['title' => 'Meme titre', 'internalcode' => 'ALL-OK', 'quantity' => 3]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertDontSee('nb-tab-attention', false);
    }

    // ------------------------------------------------------------ mismatch

    public function test_the_mismatch_tab_holds_only_differing_quantities(): void
    {
        Product::factory()->create(['sku' => 'SAME', 'quantity' => 3]);
        Product::factory()->create(['sku' => 'DIFF', 'quantity' => 7]);

        $same = $this->listing(['title' => 'Accordee', 'internalcode' => 'SAME', 'quantity' => 3]);
        $diff = $this->listing(['title' => 'En ecart', 'internalcode' => 'DIFF', 'quantity' => 2]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=qty-mismatch')
            ->assertOk()
            ->assertSee($diff->title)
            ->assertDontSee($same->title);
    }

    /** Sans correspondance, il n'y a rien à comparer : pas un écart. */
    public function test_an_unmatched_listing_is_not_a_mismatch(): void
    {
        $orphan = $this->listing(['title' => 'Orpheline', 'internalcode' => 'NOTHING-HERE', 'quantity' => 9]);
        $noCode = $this->listing(['title' => 'Sans code', 'internalcode' => null, 'quantity' => 9]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=qty-mismatch')
            ->assertOk()
            ->assertDontSee($orphan->title)
            ->assertDontSee($noCode->title);
    }

    /** Un rapprochement par préfixe compte, sur la somme des déclinaisons. */
    public function test_a_prefix_match_can_be_a_mismatch(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        foreach (['S' => 1, 'M' => 2] as $size => $qty) {
            $product->variants()->create([
                'attribute_values' => [['label' => 'Taille', 'value' => $size]],
                'sku' => 'PFX-CODE-'.$size,
                'quantity' => $qty,
            ]);
        }

        // Somme 3 contre 9 chez eux : un écart.
        $gap = $this->listing(['title' => 'Ecart par prefixe', 'internalcode' => 'PFX-CODE', 'quantity' => 9]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=qty-mismatch')
            ->assertOk()
            ->assertSee($gap->title);
    }

    public function test_a_prefix_match_that_agrees_is_not_a_mismatch(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        foreach (['S' => 1, 'M' => 2] as $size => $qty) {
            $product->variants()->create([
                'attribute_values' => [['label' => 'Taille', 'value' => $size]],
                'sku' => 'AGREE-CODE-'.$size,
                'quantity' => $qty,
            ]);
        }

        $ok = $this->listing(['title' => 'Accordee par prefixe', 'internalcode' => 'AGREE-CODE', 'quantity' => 3]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy?tab=qty-mismatch')
            ->assertOk()
            ->assertDontSee($ok->title);
    }

    /** Le compteur de l'onglet suit exactement ce que l'onglet montre. */
    public function test_the_mismatch_count_matches_the_tab_contents(): void
    {
        Product::factory()->create(['sku' => 'A', 'quantity' => 1]);
        Product::factory()->create(['sku' => 'B', 'quantity' => 5]);
        $this->listing(['internalcode' => 'A', 'quantity' => 1]);
        $this->listing(['internalcode' => 'B', 'quantity' => 2]);
        $this->listing(['internalcode' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee('Qty mismatch <span class="admin-tab-count">1</span>', false);
    }

    // -------------------------------------------------------------- resync

    public function test_the_resync_button_is_on_the_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->assertSee(route('admin.marketplaces.naturabuy.sync'), false)
            ->assertSee('Resync');
    }

    public function test_the_resync_pulls_and_reports_what_changed(): void
    {
        Http::fake(['*' => Http::response(['itemCount' => 1, 'items' => [[
            'id' => 7, 'title' => 'Nouvelle annonce', 'price' => 9.99, 'quantity' => 2,
        ]]])]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/naturabuy/sync')
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, '1 added'));

        $this->assertSame(1, NaturabuyListing::query()->count());
    }

    /** Le bouton élague : la table doit refléter exactement leur réponse. */
    public function test_the_resync_removes_what_naturabuy_dropped(): void
    {
        $this->listing(['naturabuy_id' => 999, 'title' => 'Disparue']);

        Http::fake(['*' => Http::response(['itemCount' => 1, 'items' => [[
            'id' => 7, 'title' => 'Toujours là', 'price' => 1.0,
        ]]])]);

        $this->actingAs($this->admin())->post('/admin/marketplaces/naturabuy/sync')->assertRedirect();

        $this->assertNull(NaturabuyListing::query()->where('naturabuy_id', 999)->first());
    }

    /** Un échec chez eux se dit, plutôt que de passer pour une réussite. */
    public function test_a_failed_resync_reports_the_error(): void
    {
        Http::fake(['*' => Http::response(['message' => 'nope'], 401)]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/naturabuy/sync')
            ->assertRedirect()
            ->assertSessionHasErrors('naturabuy')
            ->assertSessionMissing('status');
    }

    public function test_a_customer_cannot_trigger_a_resync(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post('/admin/marketplaces/naturabuy/sync')
            ->assertRedirect();

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------ client

    /** Leur API veut un corps JSON même en GET, sinon elle répond INVALID_JSON. */
    public function test_the_client_sends_a_json_body_on_get(): void
    {
        Http::fake(['*' => Http::response(['itemCount' => 0, 'items' => []])]);

        (new NaturabuyClient('tok', 'https://api.test'))->items();

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->body() === '{}'
                && $request->hasHeader('Authorization', 'Bearer tok');
        });
    }

    /** La pagination est par curseur : on suit `nextCursor` jusqu'à sa disparition. */
    public function test_the_client_follows_the_cursor_to_the_last_page(): void
    {
        Http::fakeSequence()
            ->push(['itemCount' => 2, 'items' => [['id' => 1], ['id' => 2]], 'nextCursor' => 'abc'])
            ->push(['itemCount' => 1, 'items' => [['id' => 3]]]);

        $items = (new NaturabuyClient('tok', 'https://api.test'))->items();

        $this->assertSame([1, 2, 3], array_column($items, 'id'));
        Http::assertSentCount(2);
    }

    public function test_the_client_stops_at_the_page_ceiling(): void
    {
        // Un curseur qui ne s'épuise jamais ne doit pas boucler à l'infini.
        Http::fake(['*' => Http::response(['itemCount' => 1, 'items' => [['id' => 1]], 'nextCursor' => 'same'])]);

        (new NaturabuyClient('tok', 'https://api.test'))->items(maxPages: 3);

        Http::assertSentCount(3);
    }

    public function test_the_client_raises_on_a_failed_response(): void
    {
        Http::fake(['*' => Http::response(['message' => 'nope'], 401)]);

        $this->expectExceptionMessage('NaturaBuy responded 401');

        (new NaturabuyClient('tok', 'https://api.test'))->items();
    }

    // ------------------------------------------------------------ sync

    public function test_the_sync_command_stores_listings(): void
    {
        Http::fake(['*' => Http::response(['itemCount' => 1, 'items' => [[
            'id' => 14738190,
            'title' => '200 cibles réactives autocollantes 76mm',
            'url' => '200-cibles-item-14738190.html',
            'category' => 2949,
            'internalcode' => '000019-1',
            'price' => 11.99,
            'oldprice' => 22.99,
            'quantity' => 0,
            'physical_quantity' => 0,
            'out_of_stock' => true,
            'out_of_stock_available' => true,
            'closed' => true,
            'variants' => [],
        ]]])]);

        $this->artisan('naturabuy:sync')->assertExitCode(0);

        $listing = NaturabuyListing::query()->firstOrFail();

        $this->assertSame(14738190, (int) $listing->naturabuy_id);
        // Les décimaux de leur API deviennent des centiemes entiers ici.
        $this->assertSame(1199, $listing->price_cents);
        $this->assertSame(2299, $listing->oldprice_cents);
        $this->assertTrue($listing->closed);
        $this->assertNotNull($listing->synced_at);
    }

    public function test_a_second_sync_updates_rather_than_duplicates(): void
    {
        $payload = fn (float $price) => ['itemCount' => 1, 'items' => [[
            'id' => 42, 'title' => 'Article', 'price' => $price, 'quantity' => 1,
        ]]];

        // Une seconde `Http::fake()` ne remplace pas la première : la
        // séquence rend l'ordre des deux réponses explicite.
        Http::fakeSequence()
            ->push($payload(10.00))
            ->push($payload(12.50));

        $this->artisan('naturabuy:sync');
        $this->artisan('naturabuy:sync');

        $this->assertSame(1, NaturabuyListing::query()->count());
        $this->assertSame(1250, NaturabuyListing::query()->firstOrFail()->price_cents);
    }

    /** Une annonce supprimée chez eux ne reviendra jamais dans la réponse. */
    public function test_prune_removes_what_naturabuy_no_longer_returns(): void
    {
        $this->listing(['naturabuy_id' => 999]);

        Http::fake(['*' => Http::response(['itemCount' => 1, 'items' => [[
            'id' => 42, 'title' => 'Toujours là', 'price' => 5.0,
        ]]])]);

        $this->artisan('naturabuy:sync --prune')->assertExitCode(0);

        $this->assertNull(NaturabuyListing::query()->where('naturabuy_id', 999)->first());
        $this->assertNotNull(NaturabuyListing::query()->where('naturabuy_id', 42)->first());
    }

    public function test_the_sync_reports_a_failure_instead_of_throwing(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        $this->artisan('naturabuy:sync')->assertExitCode(1);

        $this->assertSame(0, NaturabuyListing::query()->count());
    }
}
