<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a category is only safe when nothing depends on it — products
 * cascade-delete with their category, and a parent's own children fall back
 * to root the moment it's gone. Both cases must be blocked, not just hidden
 * behind a disabled button in the UI.
 */
class CategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->delete('/admin/categories/'.$category->id)
            ->assertRedirect('/admin/categories');

        $this->assertNull($category->fresh());
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->delete('/admin/categories/'.$category->id)
            ->assertRedirect('/admin/categories');

        $this->assertNotNull($category->fresh());
    }

    public function test_a_category_with_subcategories_cannot_be_deleted(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->admin())
            ->delete('/admin/categories/'.$parent->id)
            ->assertRedirect('/admin/categories');

        $this->assertNotNull($parent->fresh());
    }

    public function test_the_index_page_renders_blocked_and_deletable_rows(): void
    {
        $blocked = Category::factory()->create();
        Product::factory()->create(['category_id' => $blocked->id]);
        $deletable = Category::factory()->create();

        $this->actingAs($this->admin())
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Remove')
            ->assertSee($blocked->name['fr'])
            ->assertSee($deletable->name['fr']);
    }

    public function test_deleting_a_category_removes_its_stored_image(): void
    {
        $category = Category::factory()->create(['image' => 'categories/keep-me.webp']);

        $path = public_path('images/categories/keep-me.webp');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, 'fake-image-bytes');

        $this->actingAs($this->admin())
            ->delete('/admin/categories/'.$category->id)
            ->assertRedirect('/admin/categories');

        $this->assertFileDoesNotExist($path);
    }
}
