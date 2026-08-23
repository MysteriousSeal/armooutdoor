<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'onglet « Out of stock » de la liste des produits.
 *
 * Une quantité nulle ne veut pas dire qu'il n'y a plus rien à vendre : trente
 * des cinquante et une lignes listées pouvaient encore être commandées chez
 * le fournisseur. Ce qu'il faut réellement réapprovisionner se noyait dans
 * ce qui n'en avait pas besoin.
 */
class ProductOutOfStockTabTest extends TestCase
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

    private function tab()
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=out-of-stock')
            ->assertOk();
    }

    public function test_a_product_with_nothing_left_is_listed(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_a_product_the_supplier_can_fetch_is_not_listed(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);

        // Le cœur du sujet : il n'y a rien à réapprovisionner ici.
        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_a_supplier_without_the_flag_is_still_listed(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => false,
        ]);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_a_sized_product_with_one_orderable_size_is_not_listed(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 0, atSupplier: true);

        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_a_sized_product_with_no_orderable_size_is_listed(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);

        $this->tab()->assertSee($product->localizedName());
    }

    public function test_an_inactive_orderable_size_does_not_excuse_the_product(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 0, active: false, atSupplier: true);

        // Une taille retirée de la vente ne peut pas être commandée.
        $this->tab()->assertSee($product->localizedName());
    }

    public function test_a_stocked_product_is_never_listed(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);

        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_a_disabled_product_is_not_listed(): void
    {
        $product = Product::factory()->create(['quantity' => 0, 'is_active' => false]);

        $this->tab()->assertDontSee($product->localizedName());
    }

    public function test_the_tab_count_matches_the_rows(): void
    {
        Product::factory()->create(['quantity' => 0]);
        Product::factory()->create(['quantity' => 0]);
        Product::factory()->create([
            'quantity' => 0, 'supplier_id' => $this->supplier()->id, 'available_at_supplier' => true,
        ]);
        Product::factory()->create(['quantity' => 3]);

        // La règle vivait en double : le compteur et la liste doivent la lire
        // au même endroit.
        $response = $this->tab();

        $this->assertSame(2, $response->viewData('outOfStockCount'));
        $this->assertCount(2, $response->viewData('products'));
    }

    public function test_every_row_agrees_with_the_badge_it_shows(): void
    {
        Product::factory()->create(['quantity' => 0]);
        Product::factory()->create([
            'quantity' => 0, 'supplier_id' => $this->supplier()->id, 'available_at_supplier' => true,
        ]);
        Product::factory()->create(['quantity' => 4]);

        // La requête SQL et availabilityState() décident chacune de leur côté :
        // elles ne doivent jamais se contredire.
        foreach ($this->tab()->viewData('products') as $product) {
            $this->assertSame('out_of_stock', $product->availabilityState(), $product->slug);
        }
    }
}
