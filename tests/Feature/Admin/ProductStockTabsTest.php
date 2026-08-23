<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les onglets « In stock » et « At supplier ».
 *
 * Avec « Out of stock », ils découpent le catalogue actif en trois parts sans
 * recouvrement : ce qu'on a en rayon, ce qu'on peut faire venir, et ce qu'il
 * faut réapprovisionner. Chaque produit actif appartient à un seul des trois.
 */
class ProductStockTabsTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
    }

    private function variant(Product $product, int $quantity, bool $active = true, bool $atSupplier = false): void
    {
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'T', 'fr' => 'T'],
            'sku' => 'V-'.$product->id.'-'.$quantity.'-'.($atSupplier ? 's' : 'n').($active ? 'a' : 'i'),
            'quantity' => $quantity,
            'is_active' => $active,
            'supplier_id' => $atSupplier ? $this->supplier()->id : null,
            'available_at_supplier' => $atSupplier,
            'sort_order' => 1,
        ]);
    }

    private function tab(string $tab)
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab='.$tab)
            ->assertOk();
    }

    public function test_a_stocked_product_is_in_the_in_stock_tab(): void
    {
        $product = Product::factory()->create(['quantity' => 9]);

        $this->tab('in-stock')->assertSee($product->localizedName());
    }

    public function test_one_piece_left_still_counts_as_in_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 1]);

        // Choix retenu : tout ce qui est en rayon, quelle qu'en soit la
        // quantité. Sinon les derniers exemplaires n'auraient aucun onglet.
        $this->tab('in-stock')->assertSee($product->localizedName());
    }

    public function test_a_sized_product_with_one_stocked_size_is_in_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 4);

        $this->tab('in-stock')->assertSee($product->localizedName());
    }

    public function test_an_inactive_stocked_size_does_not_count(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 7, active: false);

        $this->tab('in-stock')->assertDontSee($product->localizedName());
    }

    public function test_an_orderable_product_is_in_the_at_supplier_tab(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);

        $this->tab('at-supplier')->assertSee($product->localizedName());
    }

    public function test_a_stocked_product_is_never_at_supplier(): void
    {
        // Le piège : « aucune déclinaison disponible » est vrai pour un
        // produit qui n'a aucune déclinaison, stock plein compris.
        $product = Product::factory()->create([
            'quantity' => 24,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);

        $this->tab('at-supplier')->assertDontSee($product->localizedName());
    }

    public function test_a_sized_product_only_orderable_is_at_supplier(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 0, atSupplier: true);

        $this->tab('at-supplier')->assertSee($product->localizedName());
    }

    public function test_a_plain_out_of_stock_product_is_not_at_supplier(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);

        $this->tab('at-supplier')->assertDontSee($product->localizedName());
    }

    public function test_a_disabled_product_is_in_no_stock_tab(): void
    {
        $product = Product::factory()->create(['quantity' => 5, 'is_active' => false]);

        foreach (['in-stock', 'at-supplier', 'out-of-stock'] as $tab) {
            $this->tab($tab)->assertDontSee($product->localizedName());
        }
    }

    public function test_the_three_tabs_split_the_catalogue_without_overlap(): void
    {
        Product::factory()->create(['quantity' => 9]);
        Product::factory()->create(['quantity' => 1]);
        Product::factory()->create(['quantity' => 0]);
        Product::factory()->create([
            'quantity' => 0, 'supplier_id' => $this->supplier()->id, 'available_at_supplier' => true,
        ]);
        Product::factory()->create(['quantity' => 3, 'is_active' => false]);

        $seen = collect();
        foreach (['in-stock', 'at-supplier', 'out-of-stock'] as $tab) {
            $seen = $seen->concat($this->tab($tab)->viewData('products')->pluck('id'));
        }

        // Quatre produits actifs, chacun dans exactement un onglet.
        $this->assertCount(4, $seen);
        $this->assertCount(4, $seen->unique());
        $this->assertSame(4, Product::query()->where('is_active', true)->count());
    }

    public function test_each_row_agrees_with_the_badge_it_shows(): void
    {
        Product::factory()->create(['quantity' => 9]);
        Product::factory()->create(['quantity' => 2]);
        Product::factory()->create([
            'quantity' => 0, 'supplier_id' => $this->supplier()->id, 'available_at_supplier' => true,
        ]);

        // La requête SQL et availabilityState() décident séparément.
        foreach ($this->tab('in-stock')->viewData('products') as $product) {
            $this->assertContains($product->availabilityState(), ['in_stock', 'low_stock'], $product->slug);
        }

        foreach ($this->tab('at-supplier')->viewData('products') as $product) {
            $this->assertSame('at_supplier', $product->availabilityState(), $product->slug);
        }
    }

    public function test_the_counts_match_the_rows(): void
    {
        Product::factory()->create(['quantity' => 9]);
        Product::factory()->create(['quantity' => 0]);
        Product::factory()->create([
            'quantity' => 0, 'supplier_id' => $this->supplier()->id, 'available_at_supplier' => true,
        ]);

        $response = $this->tab('in-stock');

        $this->assertSame(1, $response->viewData('inStockCount'));
        $this->assertSame(1, $response->viewData('atSupplierCount'));
        $this->assertSame(1, $response->viewData('outOfStockCount'));
        $this->assertCount(1, $response->viewData('products'));
    }

    public function test_the_tabs_are_in_the_bar(): void
    {
        $html = $this->tab('active')->getContent();

        $this->assertStringContainsString('In stock', $html);
        $this->assertStringContainsString('At supplier', $html);
        $this->assertStringContainsString('tab=in-stock', $html);
        $this->assertStringContainsString('tab=at-supplier', $html);
    }

    public function test_an_empty_tab_says_so(): void
    {
        $this->tab('at-supplier')->assertSee('No products waiting on the supplier.');
    }
}
