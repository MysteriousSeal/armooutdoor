<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where the catalogue page sends its readers.
 *
 * Fourteen pages listing every product, and the only links out of them were
 * the pager and the product pages: nothing pointed at the categories, which
 * are the pages a search engine has the best reason to rank.
 */
class CatalogueLinksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Just the row of rayons. The header menu links categories on every page
     * of the site; what is being tested here is the page's own body.
     */
    private function rails(): string
    {
        $html = $this->get('/produits')->assertOk()->getContent();

        preg_match('/<nav class="subcat-nav".*?<\/nav>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    public function test_the_rayons_are_linked_with_their_counts(): void
    {
        $category = Category::factory()->create(['name' => 'Cibles']);
        Product::factory()->count(3)->create(['category_id' => $category->id, 'is_active' => true]);

        $rails = $this->rails();

        $this->assertStringContainsString('/categories/'.$category->slug, $rails);
        $this->assertStringContainsString('Cibles', $rails);
        $this->assertStringContainsString('<span class="subcat-chip-count">3</span>', $rails);
    }

    public function test_a_category_holding_nothing_for_sale_is_not_offered(): void
    {
        $stocked = Category::factory()->create();
        Product::factory()->create(['category_id' => $stocked->id, 'is_active' => true]);

        $empty = Category::factory()->create();
        Product::factory()->create(['category_id' => $empty->id, 'is_active' => false]);

        $rails = $this->rails();

        $this->assertStringContainsString('/categories/'.$stocked->slug, $rails);
        $this->assertStringNotContainsString('/categories/'.$empty->slug, $rails);
    }

    public function test_a_parent_is_linked_for_what_its_children_hold(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        Product::factory()->count(2)->create(['category_id' => $child->id, 'is_active' => true]);

        $rails = $this->rails();

        // The parent lists what its children hold, so it counts them too.
        $this->assertStringContainsString('/categories/'.$parent->slug, $rails);
        $this->assertStringContainsString('<span class="subcat-chip-count">2</span>', $rails);
    }

    public function test_the_guides_are_offered_below_the_grid(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->get('/produits')
            ->assertOk()
            ->assertSee(route('guides.index'), false);
    }

    public function test_the_listed_products_are_named_in_the_schema(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        // A ListItem holding only a URL asks Google to fetch every page before
        // it knows what the list contains.
        $this->get('/produits')
            ->assertOk()
            ->assertSee('"name":"'.$product->localizedName().'"', false);
    }
}
