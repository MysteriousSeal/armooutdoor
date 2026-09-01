<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryFilterCollapseTest extends TestCase
{
    use RefreshDatabase;

    private function categoryWithFilterValues(int $count): Category
    {
        $category = Category::factory()->create();

        foreach (range(1, $count) as $i) {
            Product::factory()->create([
                'category_id' => $category->id,
                'is_active' => true,
                'filter_attributes' => [['label' => 'Marque', 'value' => 'Marque '.$i]],
            ]);
        }

        return $category;
    }

    public function test_groups_render_collapsed_with_a_toggle_header(): void
    {
        $category = $this->categoryWithFilterValues(3);

        $html = $this->get('/categories/'.$category->slug)->assertOk()->getContent();

        $group = Str::before(Str::after($html, 'category-filter-group '), '</fieldset>');
        $this->assertStringContainsString('data-filter-group-toggle', $group);
        $this->assertStringContainsString('aria-expanded="false"', $group);
        $this->assertStringNotContainsString('is-open', Str::before($html, 'data-filter-group-toggle'));
        // The mobile collapse's « Filtres » button is still there.
        $this->assertStringContainsString('data-filters-toggle', $html);
    }

    public function test_a_group_with_an_applied_filter_starts_open_and_names_the_value(): void
    {
        $category = $this->categoryWithFilterValues(3);

        $html = $this->get('/categories/'.$category->slug.'?filter[Marque]=Marque 2')
            ->assertOk()->getContent();

        $this->assertStringContainsString('category-filter-group is-open', $html);
        $this->assertStringContainsString('<span class="category-filter-group-active">Marque 2</span>', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
    }
}
