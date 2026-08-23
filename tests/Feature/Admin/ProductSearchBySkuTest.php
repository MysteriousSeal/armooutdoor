<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chercher un produit par sa référence dans l'administration.
 *
 * La recherche ne portait que sur le nom et le slug. La référence est
 * pourtant ce qui est imprimé sur l'article et ce que renvoient les places
 * de marché — et quarante-six produits n'ont de référence que sur leurs
 * déclinaisons, donc introuvables autrement.
 */
class ProductSearchBySkuTest extends TestCase
{
    use RefreshDatabase;

    private function search(string $term)
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?search='.urlencode($term))
            ->assertOk();
    }

    public function test_a_product_is_found_by_its_own_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'CAG-FOLGRN-BREATH']);

        $this->search('CAG-FOLGRN-BREATH')->assertSee($product->localizedName());
    }

    public function test_a_partial_sku_is_enough(): void
    {
        $product = Product::factory()->create(['sku' => 'CAG-FOLGRN-BREATH']);

        $this->search('FOLGRN')->assertSee($product->localizedName());
    }

    public function test_a_product_is_found_through_a_variant_sku(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => '10', 'fr' => '10'],
            'sku' => 'MPT-72-010',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Le cas réel : les gants Mechanix ne portent aucune référence
        // eux-mêmes, seulement une par taille.
        $this->search('MPT-72-010')->assertSee($product->localizedName());
    }

    public function test_a_product_is_listed_once_even_if_several_variants_match(): void
    {
        $product = Product::factory()->create(['sku' => null]);

        foreach (['MPT-72-010', 'MPT-72-011'] as $index => $sku) {
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'label' => ['en' => (string) $index, 'fr' => (string) $index],
                'sku' => $sku,
                'quantity' => 1,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        // whereHas et non un join : sinon le produit sortirait deux fois.
        $products = $this->search('MPT-72')->viewData('products');

        $this->assertCount(1, $products);
    }

    public function test_an_unrelated_product_is_not_returned(): void
    {
        Product::factory()->create(['sku' => 'CAG-FOLGRN-BREATH']);
        $other = Product::factory()->create(['sku' => 'TAPE-CAMO-JGL-50X45']);

        $this->search('CAG-FOLGRN-BREATH')->assertDontSee($other->localizedName());
    }

    public function test_searching_by_name_still_works(): void
    {
        $product = Product::factory()->create(['name' => ['en' => 'Balaclava', 'fr' => 'Cagoule Woodland'], 'sku' => 'X-1']);

        $this->search('Woodland')->assertSee($product->localizedName());
    }

    public function test_searching_by_slug_still_works(): void
    {
        $product = Product::factory()->create(['slug' => 'gants-mechanix-m-pact-coyote', 'sku' => 'X-2']);

        $this->search('mechanix')->assertSee($product->localizedName());
    }

    public function test_the_placeholder_mentions_the_sku(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('Name, slug or SKU…', false);
    }

    public function test_the_csv_export_narrows_the_same_way(): void
    {
        $product = Product::factory()->create(['sku' => null]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'MPT-55-009',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Product::factory()->create(['sku' => 'AUTRE-CHOSE']);

        // L'export part de la même requête : il doit suivre le filtre.
        $csv = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/export?search=MPT-55-009')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($product->localizedName(), $csv);
        $this->assertStringNotContainsString('AUTRE-CHOSE', $csv);
    }
}
