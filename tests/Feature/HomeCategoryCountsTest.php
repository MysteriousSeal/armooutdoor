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
}
