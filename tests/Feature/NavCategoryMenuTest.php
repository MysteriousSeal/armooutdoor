<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavCategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menu_lists_up_to_five_subcategories_then_counts_the_rest(): void
    {
        $root = Category::factory()->create();

        foreach (range(1, 7) as $i) {
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
            // La suite de la liste s'annonce en chiffres, dans la liste.
            ->assertSee('+ 2 autres sous-catégories')
            ->assertDontSee(__('store.nav_see_more'));
    }

    public function test_a_short_list_keeps_the_view_category_link_instead(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id, 'sort_order' => 1]);
        Product::factory()->create(['category_id' => $child->id, 'is_active' => true]);

        $this->get('/')->assertOk()
            ->assertSee(__('store.view_category'))
            ->assertDontSee('autres sous-catégories');
    }
}
