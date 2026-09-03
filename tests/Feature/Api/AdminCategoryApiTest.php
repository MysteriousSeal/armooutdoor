<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'API catégories de l'administration : elle ne savait que lister, ces
 * tests gardent la création, la modification et la suppression — avec la
 * même règle qu'au back-office : on ne supprime pas une catégorie habitée.
 */
class AdminCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'test-admin-api-token']);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-admin-api-token'];
    }

    public function test_requests_without_the_token_are_rejected(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/admin/categories', ['name' => 'Optiques'])->assertUnauthorized();
        $this->patchJson('/api/admin/categories/'.$category->id, [])->assertUnauthorized();
        $this->deleteJson('/api/admin/categories/'.$category->id)->assertUnauthorized();
    }

    public function test_a_category_can_be_created_with_a_generated_slug(): void
    {
        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Optiques de visée',
            'description' => 'Lunettes et points rouges.',
        ], $this->headers())->assertCreated();

        $response->assertJsonPath('data.name', 'Optiques de visée')
            ->assertJsonPath('data.slug', 'optiques-de-visee')
            ->assertJsonPath('data.parent_id', null);

        $this->assertDatabaseHas('categories', ['slug' => 'optiques-de-visee']);
    }

    public function test_creation_requires_name_and_description(): void
    {
        $this->postJson('/api/admin/categories', [], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'description']);
    }

    public function test_a_colliding_slug_is_suffixed_instead_of_refused(): void
    {
        Category::factory()->create(['slug' => 'optiques']);

        $this->postJson('/api/admin/categories', [
            'name' => 'Optiques',
            'description' => 'Encore des optiques.',
        ], $this->headers())->assertCreated()
            ->assertJsonPath('data.slug', 'optiques-2');
    }

    public function test_only_a_root_category_can_be_a_parent(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->postJson('/api/admin/categories', [
            'name' => 'Trop profond',
            'description' => 'Niveau trois refusé.',
            'parent_id' => $child->id,
        ], $this->headers())->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_the_buying_guide_is_writable_sanitised_and_clearable(): void
    {
        $category = Category::factory()->create();

        $this->patchJson('/api/admin/categories/'.$category->id, [
            'guide' => '<h2>Bien choisir</h2><script>alert(1)</script><p>Texte.</p>',
        ], $this->headers())->assertOk()
            ->assertJsonPath('data.guide', '<h2>Bien choisir</h2><p>Texte.</p>');

        $this->assertStringNotContainsString('<script', $category->fresh()->guide['fr']);

        // Null clears it, and an untouched PATCH leaves it alone.
        $this->patchJson('/api/admin/categories/'.$category->id, ['sort_order' => 3], $this->headers())->assertOk();
        $this->assertNotNull($category->fresh()->guide);

        $this->patchJson('/api/admin/categories/'.$category->id, ['guide' => null], $this->headers())->assertOk()
            ->assertJsonPath('data.guide', null);
        $this->assertNull($category->fresh()->guide);
    }

    public function test_a_category_can_be_edited_field_by_field(): void
    {
        $category = Category::factory()->create(['sort_order' => 3]);

        $this->patchJson('/api/admin/categories/'.$category->id, [
            'name' => 'Nouveau nom',
            'image' => 'categories/nouveau.webp',
        ], $this->headers())->assertOk()
            ->assertJsonPath('data.name', 'Nouveau nom')
            ->assertJsonPath('data.image', 'categories/nouveau.webp')
            // Les champs absents ne bougent pas.
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.slug', $category->slug);
    }

    public function test_a_parent_with_children_cannot_become_a_child(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);
        $other = Category::factory()->create();

        $this->patchJson('/api/admin/categories/'.$parent->id, [
            'parent_id' => $other->id,
        ], $this->headers())->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson('/api/admin/categories/'.$category->id, [], $this->headers())
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_a_category_with_products_or_children_is_refused(): void
    {
        $withProduct = Category::factory()->create();
        Product::factory()->create(['category_id' => $withProduct->id]);

        $withChild = Category::factory()->create();
        Category::factory()->create(['parent_id' => $withChild->id]);

        foreach ([$withProduct, $withChild] as $category) {
            $this->deleteJson('/api/admin/categories/'.$category->id, [], $this->headers())
                ->assertUnprocessable();

            $this->assertDatabaseHas('categories', ['id' => $category->id]);
        }
    }
}
