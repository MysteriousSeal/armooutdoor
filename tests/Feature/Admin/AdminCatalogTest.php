<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Product image uploads are written straight to public/images (not
     * through the Storage facade, so Storage::fake() can't intercept
     * them) — delete whatever a test actually wrote so the repo's public
     * folder isn't left polluted with test fixtures.
     */
    private function deleteProductImageFiles(?string $relativeImage): void
    {
        if ($relativeImage === null || $relativeImage === '') {
            return;
        }

        @unlink(public_path('images/'.$relativeImage));
        @unlink(public_path('images/'.dirname($relativeImage).'/thumbs/'.basename($relativeImage)));
    }

    // --- Products --------------------------------------------------------

    public function test_admin_can_create_a_product(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/products', [
                'name' => 'Gourde inox 1L',
                'description' => 'Une gourde robuste.',
                'category_id' => $category->id,
                'price' => 24.90,
                'quantity' => 15,
                'gallery_images' => [UploadedFile::fake()->image('gourde.jpg')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'category_id' => $category->id,
            'price_cents' => 2490,
            'quantity' => 15,
        ]);

        $created = Product::query()->with('images')->latest('id')->first();
        $this->deleteProductImageFiles($created?->image);
        foreach ($created?->images ?? [] as $galleryImage) {
            $this->deleteProductImageFiles($galleryImage->image);
        }
    }

    public function test_creating_a_product_requires_a_name_description_category_and_price(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/products/create')
            ->post('/admin/products', [])
            ->assertSessionHasErrors(['name', 'description', 'category_id', 'price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_a_products_sku_must_be_unique(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['sku' => 'DUP-1', 'category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->from('/admin/products/create')
            ->post('/admin/products', [
                'name' => 'Autre produit',
                'description' => 'Une description.',
                'category_id' => $category->id,
                'price' => 10,
                'sku' => 'DUP-1',
            ])
            ->assertSessionHasErrors('sku');
    }

    public function test_a_products_gtin_must_be_unique(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['gtin' => '12345678', 'category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->from('/admin/products/create')
            ->post('/admin/products', [
                'name' => 'Autre produit',
                'description' => 'Une description.',
                'category_id' => $category->id,
                'price' => 10,
                'gtin' => '12345678',
            ])
            ->assertSessionHasErrors('gtin');
    }

    public function test_a_gtin_must_be_a_valid_length(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->from('/admin/products/create')
            ->post('/admin/products', [
                'name' => 'Produit',
                'description' => 'Une description.',
                'category_id' => $category->id,
                'price' => 10,
                'gtin' => '123',
            ])
            ->assertSessionHasErrors('gtin');
    }

    public function test_admin_can_toggle_a_products_active_status(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->patch('/admin/products/'.$product->id.'/status')
            ->assertRedirect();

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_admin_can_update_a_products_quantity(): void
    {
        $product = Product::factory()->create(['quantity' => 3]);

        $this->actingAs($this->admin())
            ->patch('/admin/products/'.$product->id.'/quantity', ['quantity' => 40])
            ->assertRedirect();

        $this->assertSame(40, $product->fresh()->quantity);
    }

    public function test_product_quantity_cannot_be_negative(): void
    {
        $product = Product::factory()->create(['quantity' => 3]);

        $this->actingAs($this->admin())
            ->from('/admin/products')
            ->patch('/admin/products/'.$product->id.'/quantity', ['quantity' => -5])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_admin_products_csv_export_has_the_expected_headers(): void
    {
        Product::factory()->create(['sku' => 'EXPORT-1']);

        $response = $this->actingAs($this->admin())->get('/admin/products/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ID,Name,SKU,GTIN,Category,Supplier,Price,Stock,"Weight (g)",Active', $response->streamedContent());
        $this->assertStringContainsString('EXPORT-1', $response->streamedContent());
    }

    // --- Categories --------------------------------------------------------

    public function test_admin_can_create_a_root_category(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/categories', [
                'name' => 'Nouvelle catégorie',
                'description' => 'Une description.',
            ])
            ->assertRedirect('/admin/categories');

        $this->assertDatabaseHas('categories', ['slug' => 'nouvelle-categorie', 'parent_id' => null]);
    }

    public function test_admin_can_create_a_subcategory_under_a_root_category(): void
    {
        $root = Category::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/categories', [
                'name' => 'Sous-catégorie',
                'description' => 'Une description.',
                'parent_id' => $root->id,
            ])
            ->assertRedirect('/admin/categories');

        $this->assertDatabaseHas('categories', ['name->fr' => 'Sous-catégorie', 'parent_id' => $root->id]);
    }

    public function test_a_category_with_children_cannot_become_a_subcategory(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $otherRoot = Category::factory()->create();

        $this->actingAs($this->admin())
            ->from('/admin/categories/'.$parent->id.'/edit')
            ->put('/admin/categories/'.$parent->id, [
                'name' => $parent->name['fr'],
                'description' => 'x',
                'parent_id' => $otherRoot->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNotNull($child->exists());
    }

    public function test_category_slugs_must_be_unique(): void
    {
        Category::query()->create([
            'slug' => 'existing-slug',
            'name' => ['fr' => 'Existant'],
            'description' => ['fr' => ''],
            'sort_order' => 0,
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/categories/create')
            ->post('/admin/categories', [
                'name' => 'Autre',
                'description' => 'x',
                'slug' => 'existing-slug',
            ])
            ->assertSessionHasErrors('slug');
    }

    // --- Suppliers --------------------------------------------------------

    public function test_admin_can_create_a_supplier(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/suppliers', [
                'name' => 'Fournisseur Alpha',
                'website' => 'https://alpha.example.com',
                'lead_time_days' => 5,
            ])
            ->assertRedirect('/admin/settings/suppliers');

        $this->assertDatabaseHas('suppliers', ['name' => 'Fournisseur Alpha', 'lead_time_days' => 5]);
    }

    public function test_supplier_names_must_be_unique(): void
    {
        Supplier::query()->create(['name' => 'Fournisseur Alpha']);

        $this->actingAs($this->admin())
            ->from('/admin/settings/suppliers/create')
            ->post('/admin/settings/suppliers', ['name' => 'Fournisseur Alpha'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_delete_a_supplier(): void
    {
        $supplier = Supplier::query()->create(['name' => 'To Delete']);

        $this->actingAs($this->admin())
            ->delete('/admin/settings/suppliers/'.$supplier->id)
            ->assertRedirect('/admin/settings/suppliers');

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
