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
 * L'interrupteur « dispo fournisseur » de la liste des produits.
 *
 * Le drapeau ne se réglait que dans le panneau fournisseur du formulaire ;
 * la liste le montre et le bascule maintenant en place. Il reste refusé là
 * où il ne commanderait rien : un produit à déclinaisons (chaque taille
 * porte le sien) et un produit sans fournisseur.
 */
class ProductSupplierAvailabilityToggleTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
    }

    public function test_the_toggle_flips_the_flag_and_logs_it(): void
    {
        $product = Product::factory()->create([
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => false,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/products/'.$product->id.'/supplier-availability')
            ->assertRedirect();

        $this->assertTrue($product->fresh()->available_at_supplier);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'product.supplier_updated',
            'subject_id' => $product->id,
        ]);
    }

    public function test_a_json_request_gets_the_new_state_and_availability(): void
    {
        $product = Product::factory()->create([
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => false,
            'quantity' => 0,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson('/admin/products/'.$product->id.'/supplier-availability')
            ->assertOk()
            ->assertJson([
                'available_at_supplier' => true,
                'availability' => 'at_supplier',
                'availability_label' => 'At supplier',
            ]);
    }

    public function test_a_product_with_variants_is_refused(): void
    {
        $product = Product::factory()->create(['supplier_id' => $this->supplier()->id]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/products/'.$product->id.'/supplier-availability')
            ->assertStatus(422);
    }

    public function test_a_product_without_a_supplier_is_refused(): void
    {
        $product = Product::factory()->create(['supplier_id' => null, 'available_at_supplier' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/products/'.$product->id.'/supplier-availability')
            ->assertStatus(422);

        $this->assertFalse((bool) $product->fresh()->available_at_supplier);
    }

    public function test_the_list_renders_chip_dash_and_no_supplier_states(): void
    {
        $supplier = $this->supplier();
        Product::factory()->create(['supplier_id' => $supplier->id, 'available_at_supplier' => true]);
        Product::factory()->create(['supplier_id' => null]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('supplier-availability-chip is-on', $html);
        $this->assertStringContainsString('Assign a supplier first', $html);
        $this->assertStringContainsString('data-supplier-availability', $html);
    }

    public function test_the_status_column_is_an_icon_toggle(): void
    {
        Product::factory()->create(['is_active' => true]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('gtin-flag gtin-flag--btn is-set', $html);
        $this->assertStringContainsString('Active — click to disable', $html);
        $this->assertStringNotContainsString('badge-active', $html);
    }

    public function test_guests_cannot_toggle(): void
    {
        $product = Product::factory()->create([
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => false,
        ]);

        $this->patch('/admin/products/'.$product->id.'/supplier-availability')
            ->assertRedirect('/admin');

        $this->assertFalse((bool) $product->fresh()->available_at_supplier);
    }
}
