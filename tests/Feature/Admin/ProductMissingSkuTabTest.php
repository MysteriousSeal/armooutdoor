<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'onglet « Missing SKU » de la liste des produits.
 *
 * Un produit vendu en plusieurs tailles porte sa référence sur chacune, pas
 * sur lui-même. Le lister comme incomplet alors que toutes ses tailles sont
 * renseignées faisait de l'onglet une liste de tâches impossible à vider.
 */
class ProductMissingSkuTabTest extends TestCase
{
    use RefreshDatabase;

    private function variant(Product $product, ?string $sku, bool $active = true): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => $sku ?? 'x', 'fr' => $sku ?? 'x'],
            'sku' => $sku,
            'quantity' => 1,
            'is_active' => $active,
            'sort_order' => 1,
        ]);
    }

    private function tab()
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=no-sku')
            ->assertOk();
    }

    public function test_a_plain_product_without_a_sku_is_listed(): void
    {
        $product = Product::factory()->create(['sku' => null]);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_a_product_whose_variants_all_have_one_is_not_listed(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        $this->variant($product, 'MPT-72-010');
        $this->variant($product, 'MPT-72-011');

        // Le cœur du sujet : rien ne reste à remplir.
        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_a_product_with_one_variant_still_missing_is_listed(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        $this->variant($product, 'MPT-72-010');
        $this->variant($product, null);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_an_empty_string_counts_as_missing(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        $this->variant($product, 'MPT-72-010');
        $this->variant($product, '');

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_an_inactive_variant_without_one_still_counts(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        $this->variant($product, 'MPT-72-010');
        $this->variant($product, null, active: false);

        // Choix retenu : toute déclinaison compte, vendue ou non.
        $this->tab()->assertSee($product->localizedName());
    }

    public function test_a_product_with_its_own_sku_is_never_listed(): void
    {
        $product = Product::factory()->create(['sku' => 'CAG-FOLGRN-BREATH']);

        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_a_disabled_product_is_not_listed(): void
    {
        $product = Product::factory()->create(['sku' => null, 'is_active' => false]);

        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_the_tab_count_matches_the_rows(): void
    {
        $covered = Product::factory()->create(['sku' => null]);
        $this->variant($covered, 'A-1');

        $partial = Product::factory()->create(['sku' => null]);
        $this->variant($partial, 'B-1');
        $this->variant($partial, null);

        Product::factory()->create(['sku' => null]);
        Product::factory()->create(['sku' => 'HAS-ONE']);

        // Le compteur et la liste tenaient chacun leur propre règle : ils
        // doivent désormais lire la même.
        $response = $this->tab();

        $this->assertSame(2, $response->viewData('noSkuCount'));
        $this->assertCount(2, $response->viewData('products'));
        $this->assertFalse($response->viewData('products')->contains('id', $covered->id));
    }
}
