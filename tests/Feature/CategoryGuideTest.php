<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The category buying guide: the editorial block that turns a grid with
 * one line of description into a page a commercial search can land on.
 */
class CategoryGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_filled_guide_renders_under_the_grid_with_its_title(): void
    {
        $category = Category::factory()->create([
            'name' => ['fr' => 'Cibles'],
            'guide' => ['fr' => '<h2>Quel carton choisir</h2><p>Une cible réactive se lit de loin.</p>'],
        ]);
        \App\Models\Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->get('/categories/'.$category->slug)->assertOk()
            ->assertSee('Bien choisir : Cibles')
            ->assertSee('<h2>Quel carton choisir</h2>', false)
            ->assertSee('Une cible réactive se lit de loin.');
    }

    public function test_a_category_without_guide_shows_no_empty_section(): void
    {
        $category = Category::factory()->create(['guide' => null]);
        \App\Models\Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->get('/categories/'.$category->slug)->assertOk()
            ->assertDontSee('category-guide');
    }

    public function test_page_two_does_not_repeat_the_guide(): void
    {
        $category = Category::factory()->create([
            'guide' => ['fr' => '<p>Un guide qui ne se répète pas.</p>'],
        ]);
        \App\Models\Product::factory()->count(30)->create(['category_id' => $category->id, 'is_active' => true]);

        $this->get('/categories/'.$category->slug)->assertOk()->assertSee('category-guide');
        $this->get('/categories/'.$category->slug.'?page=2')->assertOk()->assertDontSee('category-guide');
    }

    public function test_the_admin_save_strips_what_the_guide_must_not_carry(): void
    {
        $category = Category::factory()->create(['name' => ['fr' => 'Cibles'], 'description' => ['fr' => 'Des cibles.']]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.categories.update', $category), [
                'name' => 'Cibles',
                'description' => 'Des cibles.',
                'slug' => $category->slug,
                'guide' => '<h2>Ok</h2><script>alert(1)</script><p onclick="x()">Texte.</p>',
            ]);

        $guide = $category->fresh()->guide['fr'];

        $this->assertStringContainsString('<h2>Ok</h2>', $guide);
        $this->assertStringNotContainsString('<script', $guide);
        $this->assertStringNotContainsString('onclick', $guide);
    }

    public function test_an_emptied_guide_is_stored_as_nothing(): void
    {
        $category = Category::factory()->create([
            'name' => ['fr' => 'Cibles'], 'description' => ['fr' => 'Des cibles.'],
            'guide' => ['fr' => '<p>Ancien texte.</p>'],
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.categories.update', $category), [
                'name' => 'Cibles',
                'description' => 'Des cibles.',
                'slug' => $category->slug,
                'guide' => '',
            ]);

        $this->assertNull($category->fresh()->guide);
    }
}
