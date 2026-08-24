<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La colonne « P. costs » de la liste des commandes : le coût des produits
 * vendus, tel qu'il ressort du prix moyen payé sur les bons de commande.
 *
 * Une figure de marge, distincte des coûts déduits (commission, port,
 * frais de paiement) qui ne touchent jamais à la marchandise elle-même.
 */
class OrderProductCostColumnTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            ...$overrides,
        ]);
    }

    private function line(Order $order, ?Product $product, int $quantity): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'product_slug' => $product?->slug ?? 'gone',
            'name' => $product?->name ?? ['fr' => 'Article supprimé'],
            'image' => $product?->image ?? '',
            'quantity' => $quantity,
            'unit_price_cents' => 500,
            'line_cents' => 500 * $quantity,
        ]);
    }

    private function receive(Product $product, int $quantity, int $unitCostCents): void
    {
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => 2000]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => $quantity,
            'quantity_received' => $quantity,
            'unit_cost_cents' => $unitCostCents,
        ]);
    }

    public function test_it_multiplies_each_lines_average_cost_by_its_quantity(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $this->receive($a, 10, 100); // avg 120 incl. VAT
        $this->receive($b, 10, 200); // avg 240 incl. VAT

        $order = $this->order();
        $this->line($order, $a, 3);
        $this->line($order, $b, 2);
        $order->load('items');

        $averages = Product::averagePurchaseCostsInclVatCents([$a->id, $b->id]);

        // 3*120 + 2*240 = 840
        $this->assertSame(840, $order->productCostInclVatCents($averages));
    }

    public function test_a_line_with_no_purchase_history_makes_the_whole_order_unpriceable(): void
    {
        $known = Product::factory()->create();
        $unknown = Product::factory()->create();
        $this->receive($known, 5, 100);

        $order = $this->order();
        $this->line($order, $known, 1);
        $this->line($order, $unknown, 1);
        $order->load('items');

        $averages = Product::averagePurchaseCostsInclVatCents([$known->id, $unknown->id]);

        $this->assertSame(120, [$known->id => 120][$known->id]);
        $this->assertArrayNotHasKey($unknown->id, $averages);
        $this->assertNull($order->productCostInclVatCents($averages));
    }

    public function test_a_deleted_product_line_makes_the_whole_order_unpriceable(): void
    {
        $known = Product::factory()->create();
        $this->receive($known, 5, 100);

        $order = $this->order();
        $this->line($order, $known, 1);
        $this->line($order, null, 1);
        $order->load('items');

        $averages = Product::averagePurchaseCostsInclVatCents([$known->id]);

        $this->assertNull($order->productCostInclVatCents($averages));
    }

    public function test_an_order_with_no_lines_has_no_product_cost(): void
    {
        // Aucune ligne n'est un cas différent d'un coût nul vérifié : sans
        // quoi cette commande afficherait la même puce "− 0,00 €" que celles
        // qui ont vraiment un coût connu de zéro.
        $order = $this->order();
        $order->load('items');

        $this->assertNull($order->productCostInclVatCents([]));
    }

    public function test_the_bulk_average_matches_the_per_product_one(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 19, 244);
        $this->receive($product, 1, 408);

        $bulk = Product::averagePurchaseCostsInclVatCents([$product->id]);

        $this->assertSame($product->averagePurchaseCostInclVatCents(), $bulk[$product->id]);
    }

    public function test_the_orders_list_shows_the_column_and_the_figure(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 2, 250);

        $order = $this->order();
        $this->line($order, $product, 2);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('P. costs', $html);
        // 2 unités à 3,00 € TTC (250 * 1.2) = 6,00 €.
        $this->assertStringContainsString('6,00', $html);
    }

    public function test_the_orders_list_shows_a_dash_when_a_line_cannot_be_priced(): void
    {
        $order = $this->order();
        $this->line($order, Product::factory()->create(), 1);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Missing purchase history for at least one line', $html);
    }

    public function test_profit_is_perceived_minus_product_cost(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 10, 100); // avg 120 incl. VAT

        // Perçu 1500, coût produit 2*120 = 240 -> profit 1260.
        $order = $this->order(['total_cents' => 1500]);
        $this->line($order, $product, 2);
        $order->load('items');

        $averages = Product::averagePurchaseCostsInclVatCents([$product->id]);

        $this->assertSame(240, $order->productCostInclVatCents($averages));
        $this->assertSame(1260, $order->profitInclVatCents($averages));
    }

    public function test_profit_is_null_whenever_the_product_cost_is(): void
    {
        $order = $this->order();
        $this->line($order, Product::factory()->create(), 1);
        $order->load('items');

        $this->assertNull($order->profitInclVatCents([]));
    }

    public function test_profit_can_be_negative(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 1, 5000); // avg 6000 incl. VAT

        $order = $this->order(['total_cents' => 1000]);
        $this->line($order, $product, 1);
        $order->load('items');

        $averages = Product::averagePurchaseCostsInclVatCents([$product->id]);

        $this->assertSame(1000 - 6000, $order->profitInclVatCents($averages));
    }

    public function test_the_orders_list_shows_the_profit_column(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 2, 250);

        $order = $this->order(['total_cents' => 1500]);
        $this->line($order, $product, 2);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Profit<', $html);
        // Perçu 15,00 € - coût produit 6,00 € (2 * 3,00 €) = 9,00 €.
        $this->assertStringContainsString('admin-order-profit">9,00', $html);
    }

    public function test_the_profit_kpi_card_sums_only_priced_orders(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 10, 100); // avg 120 incl. VAT

        $priceable = $this->order(['total_cents' => 1500]);
        $this->line($priceable, $product, 2); // coût 240, profit 1500-240=1260

        $unpriceable = $this->order(['total_cents' => 999]);
        $this->line($unpriceable, Product::factory()->create(), 1);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        // Le profit est une ligne du bloc « Results » depuis le regroupement
        // des cartes ; seul le montant porte la couleur.
        $this->assertStringContainsString('admin-stat-part-value is-profit', $html);
        // Seule la commande chiffrable entre dans le total : 12,60 €, sur 1
        // commande chiffrable sur 2 en tout.
        $this->assertStringContainsString('12,60', $html);
        $this->assertStringContainsString('on 1 of 2 orders', $html);
    }
}
