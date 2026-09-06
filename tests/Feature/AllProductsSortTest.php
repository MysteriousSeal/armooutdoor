<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalogue page orders itself by the same rules the category pages
 * offer: one list of orders, named once, so the two cannot drift.
 */
class AllProductsSortTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, int $cents): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'quantity' => 5,
            'name' => ['fr' => $name, 'en' => $name],
            'price_cents' => $cents,
        ]);
    }

    public function test_the_page_offers_every_order_and_defaults_to_relevance(): void
    {
        $this->product('Bâche', 1000);

        $this->get('/produits')->assertOk()
            ->assertSee('catalogue-sort', false)
            ->assertSee(__('store.sort_relevance'))
            ->assertSee(__('store.sort_name'))
            ->assertSee(__('store.sort_price_asc'))
            ->assertSee(__('store.sort_price_desc'))
            ->assertSee(__('store.sort_newest'))
            ->assertSee('<option value="relevance" selected>', false);
    }

    public function test_price_orders_the_grid_both_ways(): void
    {
        $cheap = $this->product('Zèbre', 500);
        $dear = $this->product('Alpha', 9000);

        $ascending = $this->get('/produits?sort=price-asc')->assertOk()->getContent();
        $descending = $this->get('/produits?sort=price-desc')->assertOk()->getContent();

        $this->assertLessThan(strpos($ascending, 'Alpha'), strpos($ascending, 'Zèbre'));
        $this->assertLessThan(strpos($descending, 'Zèbre'), strpos($descending, 'Alpha'));
        $this->assertTrue($cheap->exists && $dear->exists);
    }

    public function test_name_sorts_alphabetically_and_the_choice_sticks(): void
    {
        $this->product('Zèbre', 500);
        $this->product('Alpha', 9000);

        $html = $this->get('/produits?sort=name')->assertOk()
            ->assertSee('<option value="name" selected>', false)
            ->getContent();

        $this->assertLessThan(strpos($html, 'Zèbre'), strpos($html, 'Alpha'));
    }

    public function test_an_invented_order_falls_back_rather_than_failing(): void
    {
        $this->product('Bâche', 1000);

        $this->get('/produits?sort=cheapest-on-tuesdays')->assertOk()
            ->assertSee('<option value="relevance" selected>', false);
    }

    public function test_the_order_survives_the_pager(): void
    {
        Product::factory()->count(22)->create(['is_active' => true, 'quantity' => 5]);

        $this->get('/produits?sort=price-asc')->assertOk()
            ->assertSee('sort=price-asc', false);
    }
}
