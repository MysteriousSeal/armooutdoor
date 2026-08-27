<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How a category gets its address.
 *
 * The slug is what the storefront URL is built from, so it is never allowed
 * to be empty or to collide — the form lets it be left blank, and the
 * controller has to settle both cases on its own. Saving a category without
 * touching its slug must also leave that slug exactly as it was: a silent
 * suffix would move a page that is already indexed.
 */
class CategorySlugTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function create(array $overrides = [])
    {
        return $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Sacs à dos',
            'description' => 'Une description.',
            ...$overrides,
        ]);
    }

    public function test_a_second_category_with_the_same_name_gets_its_own_slug(): void
    {
        $this->create()->assertRedirect('/admin/categories');
        $this->create()->assertRedirect('/admin/categories');

        $this->assertSame(
            ['sacs-a-dos', 'sacs-a-dos-2'],
            Category::query()->orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_the_suffix_keeps_counting_rather_than_stopping_at_two(): void
    {
        $this->create();
        $this->create();
        $this->create();

        $this->assertSame(
            ['sacs-a-dos', 'sacs-a-dos-2', 'sacs-a-dos-3'],
            Category::query()->orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_a_name_that_slugs_to_nothing_still_gets_an_address(): void
    {
        // A name written entirely in punctuation, or in a script Str::slug
        // strips, would otherwise leave the category unreachable.
        $this->create(['name' => '!!!'])->assertRedirect('/admin/categories');

        $slug = Category::query()->latest('id')->firstOrFail()->slug;

        $this->assertNotSame('', $slug);
        $this->assertMatchesRegularExpression('/^category-[a-z0-9]{6}$/', $slug);
    }

    public function test_saving_a_category_again_leaves_its_slug_alone(): void
    {
        $this->create();
        $category = Category::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->put('/admin/categories/'.$category->id, [
                'name' => 'Sacs à dos et sacoches',
                'description' => 'Une autre description.',
                'slug' => $category->slug,
            ])
            ->assertRedirect('/admin/categories');

        $this->assertSame('sacs-a-dos', $category->fresh()->slug);
    }

    public function test_a_typed_slug_is_kept_as_typed(): void
    {
        $this->create(['slug' => 'randonnee'])->assertRedirect('/admin/categories');

        $this->assertSame('randonnee', Category::query()->firstOrFail()->slug);
    }

    public function test_a_subcategory_can_be_moved_back_up_to_the_root(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->admin())
            ->put('/admin/categories/'.$child->id, [
                'name' => $child->name['fr'],
                'description' => 'x',
                'slug' => $child->slug,
                // An empty select is what "no parent" posts as.
                'parent_id' => '',
            ])
            ->assertRedirect('/admin/categories');

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_a_blank_sort_order_means_first_rather_than_null(): void
    {
        $this->create()->assertRedirect('/admin/categories');

        $this->assertSame(0, Category::query()->firstOrFail()->sort_order);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->from('/admin/categories/'.$category->id.'/edit')
            ->put('/admin/categories/'.$category->id, [
                'name' => $category->name['fr'],
                'description' => 'x',
                'parent_id' => $category->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_a_subcategory_cannot_become_a_parent(): void
    {
        // Nesting stops at two levels; the menu and the breadcrumbs both
        // assume it.
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $orphan = Category::factory()->create();

        $this->actingAs($this->admin())
            ->from('/admin/categories/'.$orphan->id.'/edit')
            ->put('/admin/categories/'.$orphan->id, [
                'name' => $orphan->name['fr'],
                'description' => 'x',
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_the_edit_form_never_offers_the_category_itself_as_a_parent(): void
    {
        $category = Category::factory()->create();

        $parents = $this->actingAs($this->admin())
            ->get('/admin/categories/'.$category->id.'/edit')
            ->assertOk()
            ->viewData('parents');

        $this->assertNotContains($category->id, $parents->pluck('id')->all());
    }
}
