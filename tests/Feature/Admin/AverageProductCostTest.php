<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le coût d'achat moyen d'un produit, tel qu'il a réellement été payé sur
 * ses bons de commande reçus.
 */
class AverageProductCostTest extends TestCase
{
    use RefreshDatabase;

    private function receivedLine(Product $product, int $quantity, int $unitCostCents, int $vatBasisPoints = 2000): PurchaseOrderItem
    {
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => $vatBasisPoints]);

        return PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => $quantity,
            'quantity_received' => $quantity,
            'unit_cost_cents' => $unitCostCents,
        ]);
    }

    public function test_the_average_is_weighted_by_quantity_not_by_line_count(): void
    {
        $product = Product::factory()->create();

        // Un bon de 19 pièces à 2,44 € HT, un bon d'1 pièce à 4,08 € HT :
        // une moyenne simple des lignes donnerait (2,44+4,08)/2 = 3,26 € HT,
        // très loin du prix réellement payé pour la plupart des unités.
        $this->receivedLine($product, 19, 244);
        $this->receivedLine($product, 1, 408);

        $this->assertSame(20, $product->receivedPurchaseUnits());

        // La TVA s'applique au total de chaque ligne, pas à l'ensemble :
        // round(19*244*1.2) + round(1*408*1.2) = 5563 + 490 = 6053,
        // divisé par 20 unités = 302,65 -> 303 cents arrondis.
        $this->assertSame(303, $product->averagePurchaseCostInclVatCents());
    }

    public function test_an_unreceived_line_is_left_out(): void
    {
        $product = Product::factory()->create();
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => 2000]);

        // Commandé, jamais arrivé : un prix promis n'est pas un prix payé.
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 100,
            'quantity_received' => 0,
            'unit_cost_cents' => 1,
        ]);

        $this->assertSame(0, $product->receivedPurchaseUnits());
        $this->assertNull($product->averagePurchaseCostInclVatCents());
    }

    public function test_a_product_with_no_purchase_history_has_no_average(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($product->averagePurchaseCostInclVatCents());
    }

    public function test_a_partial_receipt_only_weighs_the_units_actually_in(): void
    {
        $product = Product::factory()->create();
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => 2000]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 10,
            'quantity_received' => 4,
            'unit_cost_cents' => 100,
        ]);

        $this->assertSame(4, $product->receivedPurchaseUnits());
        $this->assertSame(120, $product->averagePurchaseCostInclVatCents());
    }

    public function test_the_edit_page_shows_the_average_when_purchase_history_exists(): void
    {
        $product = Product::factory()->create();
        $this->receivedLine($product, 2, 250);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('product-avg-cost', $html);
        $this->assertStringContainsString('from 2 units received', $html);
    }

    public function test_the_edit_page_stays_quiet_without_purchase_history(): void
    {
        $product = Product::factory()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('product-avg-cost', $html);
    }

    public function test_the_create_page_does_not_need_the_average_at_all(): void
    {
        // Le produit n'existe pas encore : il n'a aucune ligne d'achat, et
        // la page ne doit rien tenter de calculer pour lui.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.create'))
            ->assertOk();
    }
}
