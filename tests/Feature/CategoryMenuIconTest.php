<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The icon each top-level category shows in the menu.
 *
 * The mapping is keyed by the root's slug, so a category that is renamed
 * keeps its icon but one that is re-slugged silently falls back to the
 * generic box — nothing errors, the menu just goes plain. These lock the
 * slugs the map depends on, and the inheritance a subcategory relies on.
 */
class CategoryMenuIconTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug, ?int $parentId = null): Category
    {
        return Category::query()->create([
            'slug' => $slug,
            'name' => ['fr' => $slug],
            'description' => ['fr' => ''],
            'parent_id' => $parentId,
            'sort_order' => 0,
        ]);
    }

    public function test_every_mapped_root_slug_resolves_to_its_own_icon(): void
    {
        $expected = [
            'cibles' => 'bullseye',
            'stand-de-tir' => 'toolbox',
            'vetements' => 'shirt',
            'terrain' => 'campground',
            'accessoires-de-l-arme' => 'screwdriver-wrench',
            'munitions' => 'box-open',
            'repliques-airsoft' => 'gun',
            'optiques' => 'binoculars',
        ];

        foreach ($expected as $slug => $icon) {
            $this->assertSame($icon, $this->category($slug)->iconName(), "Wrong icon for {$slug}.");
        }
    }

    public function test_a_subcategory_borrows_the_icon_of_its_root(): void
    {
        $root = $this->category('accessoires-de-l-arme');

        $this->assertSame('screwdriver-wrench', $this->category('sangles', $root->id)->iconName());
    }

    public function test_an_unmapped_category_falls_back_rather_than_failing(): void
    {
        $this->assertSame('default', $this->category('quotidien')->iconName());
    }

    public function test_every_icon_the_map_names_exists_in_the_registry(): void
    {
        // A name with no path in the registry renders the generic box, which
        // is the same failure as no mapping at all — only harder to spot.
        $registry = file_get_contents(resource_path('views/partials/icon.blade.php'));

        foreach (['bullseye', 'toolbox', 'shirt', 'campground', 'screwdriver-wrench', 'box-open', 'gun', 'binoculars'] as $icon) {
            $this->assertStringContainsString("'{$icon}' =>", $registry, "Icon {$icon} is not in the registry.");
        }
    }
}
