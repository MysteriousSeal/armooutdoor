<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a listing page names as its canonical version.
 *
 * Page two of a category used to point at page one, which told search engines
 * that everything past the first twenty products was a duplicate not worth
 * indexing — most of a catalogue of 268 products.
 */
class ListingCanonicalTest extends TestCase
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

    public function test_a_paginated_category_is_its_own_canonical(): void
    {
        $category = $this->categoryWith(45);

        $this->get('/categories/'.$category->slug.'?page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/categories/'.$category->slug).'?page=2">', false);
    }

    public function test_the_first_page_carries_no_page_number(): void
    {
        $category = $this->categoryWith(45);

        $this->get('/categories/'.$category->slug)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/categories/'.$category->slug).'">', false);
    }

    public function test_sorting_and_filtering_stay_out_of_the_canonical(): void
    {
        $category = $this->categoryWith(45);

        // Sorting reorders the same products, so every order shares one address.
        $this->get('/categories/'.$category->slug.'?sort=price_asc&page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/categories/'.$category->slug).'?page=2">', false);
    }

    public function test_a_page_number_past_the_end_names_the_page_it_actually_served(): void
    {
        // The controller pulls an out-of-range page back into the listing's
        // bounds, so ?page=999 of a three-page category *is* page three and
        // says so, rather than claiming an address that holds nothing.
        $category = $this->categoryWith(45);

        $this->get('/categories/'.$category->slug.'?page=999')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/categories/'.$category->slug).'?page=3">', false);
    }

    public function test_a_paginated_category_says_which_page_it_is_in_its_title(): void
    {
        $category = $this->categoryWith(45);

        // Self-canonicalising page two only helps if it stops wearing page
        // one's title.
        $this->get('/categories/'.$category->slug.'?page=2')
            ->assertOk()
            ->assertSee('<title>'.$category->localizedName().' — Page 2 — '.config('app.name').'</title>', false);
    }

    public function test_the_home_page_title_leads_with_the_brand(): void
    {
        // Brand first, then the tagline: a title ending with the name is the
        // one Google swaps for the bare brand on the homepage result.
        $title = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<title>Armo Outdoor : du matériel discret pour le stand et le terrain</title>',
            $title,
        );
    }
}
