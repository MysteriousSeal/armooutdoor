<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavCategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menu_lists_up_to_five_subcategories_then_says_see_more(): void
    {
        $root = Category::factory()->create();

        foreach (range(1, 6) as $i) {
            $child = Category::factory()->create([
                'parent_id' => $root->id,
                'name' => ['fr' => 'Sous-catégorie '.$i],
                'sort_order' => $i,
            ]);
            Product::factory()->create(['category_id' => $child->id, 'is_active' => true]);
        }

        $this->get('/')->assertOk()
            ->assertSee('Sous-catégorie 5')
            ->assertDontSee('Sous-catégorie 6')
            ->assertSee(__('store.nav_see_more'));
    }
}
