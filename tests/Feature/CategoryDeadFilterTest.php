<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A filter group that offers one option divides nothing. The cagoules
 * rayon showed six groups of which four were like that: every product is
 * a cagoule, in polyester, integral, in one size.
 */
class CategoryDeadFilterTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, array $attributes): Product
    {
        return Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'quantity' => 5,
            'filter_attributes' => $attributes,
        ]);
    }

    public function test_a_group_with_a_single_option_is_not_offered(): void
    {
        $category = Category::factory()->create();

        foreach (['Vert', 'Noir'] as $colour) {
            $this->product($category, [
                ['label' => 'Matière', 'value' => 'Polyester'],
                ['label' => 'Couleur', 'value' => $colour],
            ]);
        }

        $html = $this->get('/categories/'.$category->slug)->assertOk()->getContent();

        // Couleur divides the listing, Matière cannot.
        $this->assertStringContainsString('filter[Couleur]', $html);
        $this->assertStringNotContainsString('filter[Matière]', $html);
    }

    public function test_a_group_becomes_useful_the_day_a_second_value_exists(): void
    {
        $category = Category::factory()->create();

        $this->product($category, [['label' => 'Matière', 'value' => 'Polyester']]);
        $this->product($category, [['label' => 'Matière', 'value' => 'Coton']]);

        // Nothing was edited on the products: the group returns by itself.
        $this->get('/categories/'.$category->slug)->assertOk()
            ->assertSee('filter[Matière]', false);
    }

    public function test_a_value_picked_from_a_typed_url_can_still_be_let_go(): void
    {
        $category = Category::factory()->create();

        $this->product($category, [['label' => 'Matière', 'value' => 'Polyester']]);

        // The group is not offered, but a hand-typed address can still select
        // it, and the visitor needs a way out.
        $this->get('/categories/'.$category->slug.'?filter[Matière]=Polyester')
            ->assertOk()
            ->assertSee('filter[Matière]', false);
    }

    public function test_the_products_are_untouched_by_the_change(): void
    {
        $category = Category::factory()->create();
        $product = $this->product($category, [['label' => 'Matière', 'value' => 'Polyester']]);

        $this->get('/categories/'.$category->slug)->assertOk();

        // The attribute stays on the product; only its display is decided.
        $this->assertSame(
            [['label' => 'Matière', 'value' => 'Polyester']],
            $product->fresh()->filter_attributes,
        );
    }
}
