<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How many references the shop says it carries, on the home page.
 *
 * A product with variants is counted as its variants and not as itself, and
 * the figure is rounded down, since the page says "plus de".
 */
class HomeCatalogueSizeTest extends TestCase
{
    use RefreshDatabase;

    private function standalone(int $count): void
    {
        Product::factory()->count($count)->create(['is_active' => true]);
    }

    private function withVariants(int $active, int $inactive = 0): Product
    {
        $product = Product::factory()->create(['is_active' => true]);

        foreach (range(1, $active + $inactive) as $index) {
            ProductVariant::query()->forceCreate([
                'product_id' => $product->id,
                'attribute_values' => ['Taille' => 'T'.$index],
                'sku' => 'V'.$product->id.'-'.$index,
                'quantity' => 3,
                'is_active' => $index <= $active,
                'sort_order' => $index,
            ]);
        }

        return $product;
    }

    public function test_a_product_with_variants_counts_as_its_variants(): void
    {
        $this->standalone(45);
        $this->withVariants(5);

        // 45 on their own and 5 sizes: 50 references, not the 51 you get by
        // counting the parent as well.
        $catalogue = $this->get('/')->assertOk()->viewData('catalogue');

        $this->assertSame(50, $catalogue['exact']);
        $this->assertSame(50, $catalogue['rounded']);
    }

    public function test_the_parent_is_not_a_reference_of_its_own(): void
    {
        $this->standalone(44);
        $this->withVariants(5);

        // 49 references. Counting the parent too would make 50 and put a
        // band on the page, so its absence is the assertion.
        $this->get('/')->assertOk()->assertDontSee('home-stock-figure', false);
    }

    public function test_a_reference_nobody_can_buy_is_not_counted(): void
    {
        $this->standalone(45);
        Product::factory()->count(12)->create(['is_active' => false]);
        $this->withVariants(5, 7);

        $catalogue = $this->get('/')->assertOk()->viewData('catalogue');

        $this->assertSame(50, $catalogue['exact']);
    }

    public function test_the_figure_is_rounded_down_to_the_fifty(): void
    {
        $this->standalone(87);

        $this->get('/')->assertOk()
            ->assertSee('Plus de')
            ->assertSee('>50<', false)
            ->assertSee('références');
    }

    public function test_the_band_sits_under_the_offers_and_above_the_aisles(): void
    {
        Category::factory()->create();
        $this->standalone(60);

        $reduced = Product::factory()->create(['is_active' => true, 'quantity' => 4]);
        Discount::query()->create([
            'product_id' => $reduced->id,
            'type' => 'percentage',
            'value' => 20,
            'ends_at' => null,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $deals = strpos($html, '<section class="home-deals"');
        $stock = strpos($html, '<section class="home-stock"');
        $aisles = strpos($html, '<section class="home-cats-section"');

        $this->assertNotFalse($deals, 'The offers row should be on the page for this test to mean anything.');
        $this->assertNotFalse($aisles);
        $this->assertLessThan($stock, $deals, 'The band belongs under the offers row.');
        $this->assertLessThan($aisles, $stock, 'And above the aisles.');
    }

    public function test_the_band_says_what_the_figure_is_spread_across(): void
    {
        $rayon = Category::factory()->create(['parent_id' => null]);
        $shelf = Category::factory()->create(['parent_id' => $rayon->id]);
        // A rayon and a subcategory nobody can reach, since neither holds a
        // product a visitor can buy.
        $empty = Category::factory()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => $empty->id]);

        Product::factory()->count(60)->create(['is_active' => true, 'category_id' => $shelf->id]);

        $catalogue = $this->get('/')->assertOk()->viewData('catalogue');

        // The rayon counts through its child; the empty pair counts for
        // nothing.
        $this->assertSame(1, $catalogue['rayons']);
        $this->assertSame(1, $catalogue['categories']);
    }

    public function test_the_band_leads_to_the_catalogue(): void
    {
        $this->standalone(60);

        $this->get('/')->assertOk()
            ->assertSee('Voir tout le catalogue')
            ->assertSee(route('products.all'), false);
    }

    /**
     * The sentence beside the figure weights its own second half: the range
     * is a fact and where it ships from is a promise. It is interpolated raw
     * to carry that — were escaping to come back, the tag would print as
     * text in the middle of the page.
     */
    public function test_the_promise_is_stamped_inside_the_sentence(): void
    {
        $this->standalone(60);

        $this->get('/')->assertOk()
            ->assertSee('<strong>Tout est expédié depuis la France.</strong>', false)
            ->assertDontSee('&lt;strong&gt;', false);
    }
}
