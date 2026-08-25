<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Approvisionnement en cours ».
 *
 * Un article déjà commandé chez le fournisseur ne se recommande pas : la
 * boutique le dit, et refuse la mise au panier même quand le fournisseur
 * pourrait servir.
 */
class RestockingBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
    }

    private function outOfStockProduct(): Product
    {
        return Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);
    }

    private function purchaseLine(Product $product, string $status, int $ordered = 10, int $received = 0): PurchaseOrderItem
    {
        $po = PurchaseOrder::factory()->create(['status' => $status]);

        return PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => $ordered,
            'quantity_received' => $received,
            'unit_cost_cents' => 500,
        ]);
    }

    public function test_an_open_purchase_order_puts_the_product_in_restocking(): void
    {
        $product = $this->outOfStockProduct();
        $this->purchaseLine($product, 'sent');

        $this->assertTrue($product->isRestocking());
        $this->assertSame('restocking', $product->availabilityState());
    }

    /** Un brouillon n'engage rien : ce n'est pas encore un approvisionnement. */
    public function test_a_draft_purchase_order_does_not_count(): void
    {
        $product = $this->outOfStockProduct();
        $this->purchaseLine($product, 'draft');

        $this->assertFalse($product->isRestocking());
        $this->assertSame('at_supplier', $product->availabilityState());
    }

    /** Ligne par ligne : une ligne servie n'attend plus rien. */
    public function test_a_fully_received_line_no_longer_counts(): void
    {
        $product = $this->outOfStockProduct();
        $this->purchaseLine($product, 'partially_received', ordered: 10, received: 10);

        $this->assertFalse($product->isRestocking());
    }

    public function test_a_partially_received_line_still_counts(): void
    {
        $product = $this->outOfStockProduct();
        $this->purchaseLine($product, 'partially_received', ordered: 10, received: 4);

        $this->assertTrue($product->isRestocking());
    }

    /** Le cœur du sujet : plus commandable, même servi par le fournisseur. */
    public function test_a_restocking_product_cannot_be_backordered(): void
    {
        $product = $this->outOfStockProduct();

        $this->assertTrue($product->isBackorderable());

        $this->purchaseLine($product, 'sent');

        $this->assertFalse($product->fresh()->isBackorderable());
        $this->assertSame(0, $product->fresh()->maxPurchasable());
    }

    public function test_the_cart_refuses_a_restocking_product(): void
    {
        $product = $this->outOfStockProduct();
        $this->purchaseLine($product, 'sent');

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->assertSame(0, app(Cart::class)->quantity());
    }

    public function test_the_product_page_shows_the_restocking_badge(): void
    {
        $product = $this->outOfStockProduct();
        $this->purchaseLine($product, 'sent');

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('stock-badge is-restocking', false)
            ->assertSee(__('store.restocking'))
            ->assertDontSee(__('store.available_at_supplier'))
            ->assertDontSee(__('store.out_of_stock'));
    }

    public function test_the_card_shows_the_restocking_badge(): void
    {
        $product = $this->outOfStockProduct();
        $this->purchaseLine($product, 'sent');

        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('card-stock-chip is-restocking', false)
            ->assertSee(__('store.card_restocking'));
    }

    /** Une déclinaison se juge sur ses propres commandes fournisseur. */
    public function test_a_variant_in_restocking_is_shown_and_refused(): void
    {
        $supplier = $this->supplier();
        $product = Product::factory()->create(['quantity' => 0, 'sku' => null]);
        $variant = $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'TEE-M',
            'quantity' => 0,
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
        ]);

        $this->assertTrue($variant->isBackorderable());

        $po = PurchaseOrder::factory()->create(['status' => 'sent']);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => 'TEE M',
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_cost_cents' => 500,
        ]);

        $this->assertTrue($variant->fresh()->isRestocking());
        $this->assertFalse($variant->fresh()->isBackorderable());
        $this->assertSame('restocking', $product->fresh()->availabilityState());

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('is-restocking', false)
            ->assertSee(__('store.variant_stock_restocking'));
    }

    /** Le listing ne doit pas interroger la base une fois par produit. */
    public function test_the_listing_does_not_query_per_product(): void
    {
        foreach (range(1, 8) as $i) {
            $this->outOfStockProduct();
        }

        \DB::enableQueryLog();
        $this->get('/nouveautes')->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThan(60, $queries, "Ran {$queries} queries for 8 products");
    }

    /** Sans commande en cours, rien ne change pour les autres articles. */
    public function test_an_untouched_product_keeps_its_supplier_badge(): void
    {
        $product = $this->outOfStockProduct();

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('stock-badge is-at-supplier', false)
            ->assertDontSee(__('store.restocking'));
    }
}
