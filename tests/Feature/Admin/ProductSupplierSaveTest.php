<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving the supplier panel on its own. The point is to note a purchase price
 * without pushing the whole product form, so the endpoint must write that
 * block and leave everything else exactly as it was.
 */
class ProductSupplierSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_supplier_block_is_saved_on_its_own(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::query()->create(['name' => 'DM Diffusion']);
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.products.supplier', $product), [
                'supplier_id' => $supplier->id,
                'available_at_supplier' => 1,
                'supplier_reference' => '25637',
                'supplier_product_url' => 'https://example.test/p/25637',
                'supplier_price' => '49.90',
                'markup_percent' => '30',
            ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $fresh = $product->fresh();
        $this->assertSame($supplier->id, $fresh->supplier_id);
        $this->assertSame('25637', $fresh->supplier_reference);
        $this->assertSame(4990, $fresh->supplier_price_cents);
        $this->assertSame(3000, $fresh->markup_basis_points);
        $this->assertTrue($fresh->available_at_supplier);
    }

    public function test_nothing_outside_the_supplier_block_is_touched(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price_cents' => 9900, 'quantity' => 7]);
        $before = $product->only(['name', 'description', 'price_cents', 'quantity', 'sku', 'category_id', 'image']);

        // C'est tout l'intérêt : noter un prix d'achat ne doit pas republier
        // la description, les photos ni le prix de vente.
        $this->actingAs($admin)
            ->patchJson(route('admin.products.supplier', $product), ['supplier_price' => '12.00'])
            ->assertOk();

        $this->assertSame($before, $product->fresh()->only(array_keys($before)));
    }

    public function test_clearing_a_field_clears_the_stored_value(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'supplier_price_cents' => 4990,
            'markup_basis_points' => 3000,
            'supplier_reference' => 'OLD-REF',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.products.supplier', $product), [
                'supplier_price' => '',
                'markup_percent' => '',
                'supplier_reference' => '',
            ])
            ->assertOk();

        $fresh = $product->fresh();
        $this->assertNull($fresh->supplier_price_cents);
        $this->assertNull($fresh->markup_basis_points);
        $this->assertNull($fresh->supplier_reference);
    }

    public function test_invalid_input_comes_back_as_field_errors(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['supplier_price_cents' => 4990]);

        // Le script réaffiche ces messages sous les champs, donc ils doivent
        // arriver par champ et non comme une erreur générique.
        $this->actingAs($admin)
            ->patchJson(route('admin.products.supplier', $product), [
                'supplier_price' => '-1',
                'supplier_product_url' => 'not-a-url',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_price', 'supplier_product_url']);

        $this->assertSame(4990, $product->fresh()->supplier_price_cents, 'A refused save must change nothing.');
    }

    public function test_a_product_with_variants_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => 'M',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Les données fournisseur vivent sur les variantes une fois qu'il y en
        // a : les écrire ici les ferait effacer au prochain enregistrement.
        $this->actingAs($admin)
            ->patchJson(route('admin.products.supplier', $product), ['supplier_price' => '10.00'])
            ->assertForbidden();

        $this->assertNull($product->fresh()->supplier_price_cents);
    }

    public function test_a_signed_out_visitor_cannot_save(): void
    {
        $product = Product::factory()->create();

        $this->patchJson(route('admin.products.supplier', $product), ['supplier_price' => '10.00'])
            ->assertRedirect();

        $this->assertNull($product->fresh()->supplier_price_cents);
    }

    public function test_the_save_is_recorded_in_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.products.supplier', $product), ['supplier_price' => '10.00']);

        $this->assertTrue(
            AdminActivityLog::query()->where('action', 'product.supplier_updated')->exists(),
        );
    }

    public function test_the_button_and_its_modal_are_on_the_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('id="supplier-save-row"', false)
            ->assertSee('id="supplier-save-modal"', false)
            ->assertSee(route('admin.products.supplier', $product), false);
    }

    public function test_the_button_is_absent_where_it_would_be_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => 'M',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertDontSee('id="supplier-save-row"', false)
            ->assertDontSee('id="supplier-save-modal"', false);
    }
}
