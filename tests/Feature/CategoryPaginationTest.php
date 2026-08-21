<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pagination des fiches catégorie, vingt produits par page.
 *
 * Les filtres et le tri s'appliquent en PHP sur la collection entière, donc le
 * découpage arrive après eux : une page doit refléter le résultat filtré et
 * trié, pas les vingt premiers produits de la catégorie.
 */
class CategoryPaginationTest extends TestCase
{
    private function categoryWith(int $count): Category
    {
        $category = Category::factory()->create();

        Product::factory()->count($count)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        return $category;
    }

    use RefreshDatabase;

    public function test_a_page_holds_twenty_products(): void
    {
        $category = $this->categoryWith(25);

        $products = $this->get('/categories/'.$category->slug)->assertOk()->viewData('products');

        $this->assertCount(20, $products);
        $this->assertSame(25, $products->total());
        $this->assertSame(2, $products->lastPage());
    }

    public function test_the_second_page_holds_the_remainder(): void
    {
        $category = $this->categoryWith(25);

        $products = $this->get('/categories/'.$category->slug.'?page=2')->assertOk()->viewData('products');

        $this->assertCount(5, $products);
        $this->assertSame(2, $products->currentPage());
    }

    public function test_no_product_is_shown_twice_or_skipped(): void
    {
        $category = $this->categoryWith(45);

        $seen = collect();

        foreach ([1, 2, 3] as $page) {
            $seen = $seen->concat(
                $this->get('/categories/'.$category->slug.'?page='.$page)->viewData('products')->pluck('id')
            );
        }

        // Le vrai risque d'une pagination faite à la main : un décalage d'un
        // rang qui saute un produit ou en montre un deux fois.
        $this->assertCount(45, $seen);
        $this->assertCount(45, $seen->unique());
    }

    public function test_the_pager_is_absent_when_everything_fits(): void
    {
        $category = $this->categoryWith(12);

        $this->get('/categories/'.$category->slug)
            ->assertOk()
            ->assertDontSee('store-pager', false);
    }

    public function test_the_pager_appears_once_there_is_more_than_one_page(): void
    {
        $category = $this->categoryWith(25);

        $this->get('/categories/'.$category->slug)
            ->assertOk()
            ->assertSee('store-pager', false)
            ->assertSee(__('store.pagination_next'));
    }

    public function test_a_page_beyond_the_last_falls_back_instead_of_showing_nothing(): void
    {
        $category = $this->categoryWith(25);

        // Une URL collée à la main ne doit pas donner une grille vide sous un
        // pager qui annonce deux pages.
        $products = $this->get('/categories/'.$category->slug.'?page=99')->assertOk()->viewData('products');

        $this->assertSame(2, $products->currentPage());
        $this->assertNotEmpty($products);
    }

    public function test_a_nonsense_page_falls_back_to_the_first(): void
    {
        $category = $this->categoryWith(25);

        foreach (['0', '-3', 'abc'] as $page) {
            $products = $this->get('/categories/'.$category->slug.'?page='.$page)->assertOk()->viewData('products');
            $this->assertSame(1, $products->currentPage(), 'page='.$page);
        }
    }

    public function test_the_sort_survives_in_the_pager_links(): void
    {
        $category = $this->categoryWith(25);

        // Sans cela, passer en page 2 ramènerait au tri par défaut.
        $this->get('/categories/'.$category->slug.'?sort=price-asc')
            ->assertOk()
            ->assertSee('sort=price-asc', false);
    }

    public function test_paging_happens_after_sorting_not_before(): void
    {
        $category = Category::factory()->create();

        foreach (range(1, 25) as $i) {
            Product::factory()->create([
                'category_id' => $category->id,
                'is_active' => true,
                'price_cents' => $i * 100,
            ]);
        }

        $first = $this->get('/categories/'.$category->slug.'?sort=price-asc')->viewData('products');
        $last = $this->get('/categories/'.$category->slug.'?sort=price-desc')->viewData('products');

        // Découper avant de trier donnerait la même première page dans les
        // deux sens, simplement réordonnée.
        $this->assertSame(100, $first->first()->price_cents);
        $this->assertSame(2500, $last->first()->price_cents);
    }

    public function test_the_header_count_shows_the_total_not_the_page(): void
    {
        $category = $this->categoryWith(25);

        // "20 pièces" en tête d'une catégorie qui en compte 25 serait faux.
        $this->get('/categories/'.$category->slug)
            ->assertOk()
            ->assertSee(trans_choice('store.products_count', 25, ['count' => 25]));
    }
}
