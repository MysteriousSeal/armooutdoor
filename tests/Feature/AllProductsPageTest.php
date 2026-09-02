<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllProductsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_lists_the_whole_catalogue_paginated(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(21)->create(['category_id' => $category->id, 'is_active' => true]);
        Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);

        $response = $this->get('/produits')->assertOk()
            ->assertSee('Tous les produits');

        $products = $response->viewData('products');
        // 21 active products: 20 on page one, the disabled one nowhere.
        $this->assertSame(21, $products->total());
        $this->assertCount(20, $products->items());

        $this->get('/produits?page=2')->assertOk();
    }

    public function test_the_home_page_sends_every_products_link_there(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(route('products.all'), $html);
        // The old destination is gone from the generic links: the first
        // category may still appear as itself, but not as « the shop ».
        $this->assertStringContainsString('Voir tous les produits', $html);
    }

    public function test_the_pages_sitemap_names_it(): void
    {
        $this->get('/sitemap-pages.xml')->assertOk()
            ->assertSee(route('products.all'));
    }
}
