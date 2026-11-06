<?php

namespace Tests\Feature\Admin;

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

    private function variant(Product $product, array $attributes = []): ProductVariant
    {
        return ProductVariant::query()->create($attributes + [
            'product_id' => $product->id,
            'sku' => 'VAR-'.$product->id.'-'.uniqid(),
            'quantity' => 0,
            'is_active' => true,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
        ]);
    }

    public function test_a_variant_toggle_flips_its_own_flag_and_redraws_the_product(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $variant = $this->variant($product, ['supplier_id' => $this->supplier()->id, 'available_at_supplier' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson('/admin/products/'.$product->id.'/variants/'.$variant->id.'/supplier-availability')
            ->assertOk()
            ->assertJson([
                'available_at_supplier' => true,
                // The only size, now sourceable: the product reads « At supplier ».
                'availability' => 'at_supplier',
                'availability_label' => 'At supplier',
            ]);

        $this->assertTrue($variant->fresh()->available_at_supplier);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'product.supplier_updated',
            'subject_id' => $product->id,
        ]);
    }

    public function test_a_variant_without_a_supplier_or_of_another_product_is_refused(): void
    {
        $product = Product::factory()->create();
        $bare = $this->variant($product);
        $other = $this->variant(Product::factory()->create(), ['supplier_id' => $this->supplier()->id, 'available_at_supplier' => false]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/products/'.$product->id.'/variants/'.$bare->id.'/supplier-availability')
            ->assertStatus(422);
        $this->actingAs($admin)
            ->patch('/admin/products/'.$product->id.'/variants/'.$other->id.'/supplier-availability')
            ->assertNotFound();

        $this->assertFalse((bool) $other->fresh()->available_at_supplier);
    }

    public function test_the_variant_panel_shows_the_switch_and_photo_count_not_the_supplier(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur Zeta', 'lead_time_days' => 5]);
        $product = Product::factory()->create();
        $variant = $this->variant($product, [
            'supplier_id' => $supplier->id,
            'supplier_reference' => 'REF-ZETA-42',
            'available_at_supplier' => true,
            'image' => 'products/a.webp',
            'image_2' => 'products/b.webp',
        ]);
        $this->variant($product);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->assertSee(route('admin.products.variants.supplier-availability', [$product, $variant]), false)
            ->assertSee('title="2 photos on our shop, five wanted">2</span>', false)
            ->assertSee('title="No photo on our shop, five wanted">0</span>', false)
            ->assertSee('Assign a supplier first')
            ->assertDontSee('Supplier ref.')
            // The supplier's name stays in the filter dropdown, not in the panel.
            ->assertDontSee('REF-ZETA-42');
    }

    public function test_a_variant_full_photos_tick_flips_its_own_mark(): void
    {
        $product = Product::factory()->create(['full_photos' => false]);
        $variant = $this->variant($product);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/products')
            ->assertOk()
            ->assertSee(route('admin.products.variants.full-photos', [$product, $variant]), false);

        $this->actingAs($admin)
            ->patchJson('/admin/products/'.$product->id.'/variants/'.$variant->id.'/full-photos')
            ->assertOk()
            // The only variant now ticked: the product counts toward the header.
            ->assertJson(['full_photos' => true, 'full_photos_stats' => ['count' => '1', 'percent' => '100.000']]);

        $this->assertTrue($variant->fresh()->full_photos);
        // The product keeps its own mark.
        $this->assertFalse($product->fresh()->full_photos);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'product.full_photos_updated', 'subject_id' => $product->id]);

        $this->actingAs($admin)
            ->patch('/admin/products/'.$product->id.'/variants/'.$variant->id.'/full-photos')
            ->assertRedirect();
        $this->assertFalse($variant->fresh()->full_photos);
    }

    public function test_a_product_in_variants_counts_once_every_active_variant_is_ticked(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['full_photos' => false]);
        $first = $this->variant($product, ['full_photos' => true]);
        $second = $this->variant($product, ['full_photos' => false]);
        // An inactive variant is not waited for.
        $this->variant($product, ['full_photos' => false, 'is_active' => false]);
        // A plain product, ticked, and an inactive one, left out of both figures.
        Product::factory()->create(['full_photos' => true]);
        Product::factory()->create(['full_photos' => true, 'is_active' => false]);

        $stats = fn () => $this->actingAs($admin)->get('/admin/products')->assertOk()->viewData('fullPhotosStats');

        // One of two variants ticked: only the plain product counts, 1 of 2.
        $this->assertSame(['count' => '1', 'percent' => '50.000'], $stats());

        $this->actingAs($admin)
            ->patchJson('/admin/products/'.$product->id.'/variants/'.$second->id.'/full-photos')
            ->assertOk()
            ->assertJson(['full_photos_stats' => ['count' => '2', 'percent' => '100.000']]);

        // Unticking one variant takes the product back out.
        $this->actingAs($admin)
            ->patchJson('/admin/products/'.$product->id.'/variants/'.$first->id.'/full-photos')
            ->assertJson(['full_photos_stats' => ['count' => '1', 'percent' => '50.000']]);
    }

    public function test_a_product_in_variants_still_counts_by_its_own_tick(): void
    {
        $product = Product::factory()->create(['full_photos' => true]);
        $this->variant($product, ['full_photos' => false]);

        $this->assertSame(
            ['count' => '1', 'percent' => '100.000'],
            $this->actingAs(User::factory()->admin()->create())->get('/admin/products')->viewData('fullPhotosStats'),
        );
    }

    public function test_a_variant_of_another_product_cannot_be_marked(): void
    {
        $variant = $this->variant(Product::factory()->create());

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/products/'.Product::factory()->create()->id.'/variants/'.$variant->id.'/full-photos')
            ->assertNotFound();

        $this->assertFalse($variant->fresh()->full_photos);
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
