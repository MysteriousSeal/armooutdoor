<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The address a listing keeps when it is asked for the order it was in.
 *
 * The sort selector is a plain form: choosing « Pertinence » submits it like
 * any other value and produces a second address for the page the visitor was
 * already on. One of the two is enough.
 */
class ListingDefaultSortRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function categoryWith(int $count): Category
    {
        $category = Category::factory()->create();

        Product::factory()->count($count)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        return $category;
    }

    public function test_the_catalogue_drops_a_default_sort(): void
    {
        $this->categoryWith(3);

        $this->get('/produits?sort=relevance')
            ->assertRedirect(url('/produits'))
            ->assertStatus(301);
    }

    public function test_a_category_drops_a_default_sort(): void
    {
        $category = $this->categoryWith(3);

        $this->get('/categories/'.$category->slug.'?sort=relevance')
            ->assertRedirect(url('/categories/'.$category->slug))
            ->assertStatus(301);
    }

    public function test_the_rest_of_the_address_survives(): void
    {
        $this->categoryWith(45);

        // Only the sort is redundant; the page the visitor was reading is not.
        $this->get('/produits?sort=relevance&page=2')
            ->assertRedirect(url('/produits').'?page=2');
    }

    public function test_a_real_sort_is_served_where_it_was_asked_for(): void
    {
        $this->categoryWith(3);

        $this->get('/produits?sort=price-asc')->assertOk();
    }

    public function test_an_unknown_sort_is_not_treated_as_the_default(): void
    {
        $this->categoryWith(3);

        // It falls back to the default order once served, but it is not an
        // address the shop generates, so there is nothing to send it to.
        $this->get('/produits?sort=nonsense')->assertOk();
    }
}
