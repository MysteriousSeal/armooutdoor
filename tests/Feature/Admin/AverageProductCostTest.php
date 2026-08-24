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

    public function test_the_breakdown_page_lists_each_line_and_matches_the_average(): void
    {
        $product = Product::factory()->create();
        $first = $this->receivedLine($product, 2, 250);
        $second = $this->receivedLine($product, 3, 286);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.average-cost', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($first->purchaseOrder->number, $html);
        $this->assertStringContainsString($second->purchaseOrder->number, $html);

        // 2*250 -> round(500*1.2)=600 ; 3*286 -> round(858*1.2)=1030.
        // Total 1630, sur 5 unités -> 326, comme la moyenne affichée.
        $this->assertStringContainsString('16,30', $html);
        $this->assertSame(326, $product->averagePurchaseCostInclVatCents());
        $this->assertStringContainsString('3,26', $html);
    }

    public function test_the_breakdown_page_is_empty_without_any_receipt(): void
    {
        $product = Product::factory()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.average-cost', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No purchase order has been received', $html);
    }

    public function test_the_edit_page_links_to_the_breakdown(): void
    {
        $product = Product::factory()->create();
        $this->receivedLine($product, 2, 250);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.products.average-cost', $product), $html);
    }

    public function test_the_average_folds_in_the_orders_prorated_charges(): void
    {
        // Un bon avec deux produits, 5 et 15 unités, 1200 de frais
        // supplémentaires : la ligne de 5 en absorbe 300, celle de 15, 900.
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => 2000, 'additional_costs_cents' => 1200]);

        $product = Product::factory()->create();
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 5,
            'quantity_received' => 5,
            'unit_cost_cents' => 100,
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'Other',
            'quantity_ordered' => 15,
            'quantity_received' => 15,
            'unit_cost_cents' => 100,
        ]);

        // (5*100 + 300) * 1.2 / 5 = 192 cents l'unité.
        $this->assertSame(192, $product->averagePurchaseCostInclVatCents());
    }

    public function test_a_product_alone_on_its_order_absorbs_the_full_charge(): void
    {
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => 2000, 'discount_cents' => 200]);
        $product = Product::factory()->create();
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 2,
            'quantity_received' => 2,
            'unit_cost_cents' => 100,
        ]);

        // (2*100 - 200) * 1.2 / 2 = 0.
        $this->assertSame(0, $product->averagePurchaseCostInclVatCents());
    }

    public function test_the_breakdown_page_shows_the_charges_share_per_line(): void
    {
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => 2000, 'additional_costs_cents' => 1200]);
        $product = Product::factory()->create();
        $line = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 5,
            'quantity_received' => 5,
            'unit_cost_cents' => 100,
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'Other',
            'quantity_ordered' => 15,
            'quantity_received' => 15,
            'unit_cost_cents' => 100,
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.average-cost', $product))
            ->assertOk()
            ->getContent();

        $this->assertSame(300, $po->fresh(['items'])->lineShareOfChargesCents($line->fresh()));
        $this->assertStringContainsString('+3,60', $html); // 300 HT -> withVat 20% = 360.
        $this->assertStringContainsString('9,60', $html); // (500+300)*1.2/100 = 9,60 €.
    }
}
