<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's category cards need a name, an icon and a number.
 *
 * They used to get that number by loading every active product of every
 * category — with its variants and its suppliers — and calling count() on the
 * result. The number is now counted in SQL. These tests hold the count to what
 * it was, and hold the catalogue out of memory.
 */
class HomeCategoryCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_does_not_hydrate_the_catalogue(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $categories = $this->get('/')->assertOk()->viewData('categories');

        foreach ($categories as $each) {
            $this->assertFalse(
                $each->relationLoaded('products'),
                $each->slug.' charge ses produits pour en afficher le nombre'
            );
        }
    }

    public function test_the_count_is_available_without_the_products(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $categories = $this->get('/')->assertOk()->viewData('categories');
        $found = $categories->firstWhere('slug', $category->slug);

        $this->assertSame(3, $found->listingCount());
    }

    public function test_an_inactive_product_is_not_counted(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $categories = $this->get('/')->assertOk()->viewData('categories');

        $this->assertSame(2, $categories->firstWhere('slug', $category->slug)->listingCount());
    }

    public function test_a_parent_counts_what_its_children_hold(): void
    {
        $parent = Category::factory()->create(['parent_id' => null]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        Product::factory()->count(4)->create([
            'category_id' => $child->id,
            'is_active' => true,
        ]);

        $categories = $this->get('/')->assertOk()->viewData('categories');

        $this->assertSame(4, $categories->firstWhere('slug', $parent->slug)->listingCount());
    }

    public function test_the_loaded_relation_remains_a_fallback(): void
    {
        // A caller that eager-loads products instead of counting them must
        // still get a number: the count attribute is an optimisation, not a
        // new requirement.
        $category = Category::factory()->create(['parent_id' => null]);
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $loaded = Category::query()->with('products')->find($category->id);

        $this->assertNull($loaded->products_count);
        $this->assertSame(2, $loaded->listingCount());
    }

    /**
     * The card shows that number rather than only counting it.
     *
     * The blurb says what an aisle holds; the count says whether it is worth
     * walking down. It used to be a fallback shown only when a category had
     * neither a written blurb nor a description, which meant the categories
     * best described were the ones that said least about their size.
     */
    public function test_the_card_shows_how_much_is_behind_the_door(): void
    {
        $category = Category::factory()->create([
            'parent_id' => null,
            'description' => ['fr' => 'Une description bien à elle.'],
        ]);
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('home-cat-count', false)
            ->assertSee('Une description bien à elle.', false)
            ->assertSee(trans_choice('store.products_count', 3, ['count' => 3]), false);
    }

    /**
     * The cards carry an olive edge down their left and no shadow. Both
     * matter: the shadow was the last of the soft styling on a page that had
     * dropped it everywhere else, and the edge is what replaced it.
     */
    public function test_the_cards_are_marked_by_an_edge_and_not_by_a_shadow(): void
    {
        $css = file_get_contents(public_path('css/home.css'));
        $start = strpos($css, '.home .home-cat {');
        $rule = substr($css, $start, strpos($css, '}', $start) - $start);

        $this->assertMatchesRegularExpression('/border-left:\s*3px solid/', $rule);
        $this->assertStringNotContainsString('box-shadow', $rule);
    }
}
