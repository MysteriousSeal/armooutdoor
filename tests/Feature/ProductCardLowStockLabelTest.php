<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The low-stock and supplier-availability chips' long labels wrap and crowd
 * the price below 640px, so a shorter one takes over there via a CSS-only
 * swap (no JS, no viewport-dependent server render).
 */
class ProductCardLowStockLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_low_stock_card_renders_both_labels_for_the_css_swap(): void
    {
        Product::factory()->create(['quantity' => 1]);

        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('<span class="card-stock-chip-full">'.__('store.low_stock').'</span>', false)
            ->assertSee('<span class="card-stock-chip-short">'.__('store.low_stock_short').'</span>', false);
    }

    public function test_a_supplier_available_card_renders_both_labels_for_the_css_swap(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
        ]);

        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('<span class="card-stock-chip-full">'.__('store.card_available_at_supplier').'</span>', false)
            ->assertSee('<span class="card-stock-chip-short">'.__('store.card_available_at_supplier_short').'</span>', false);
    }

    public function test_a_restocking_card_renders_both_labels_for_the_css_swap(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $product = Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
        ]);
        $po = PurchaseOrder::factory()->create(['status' => 'sent']);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost_cents' => 500,
        ]);

        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('<span class="card-stock-chip-full">'.__('store.card_restocking').'</span>', false)
            ->assertSee('<span class="card-stock-chip-short">'.__('store.card_restocking_short').'</span>', false);
    }

    public function test_the_short_label_is_hidden_by_default_and_shown_below_640px(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.card-stock-chip-short\s*\{[^}]*display:\s*none/', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 640px\) \{[^}]*\.card-stock-chip-full\s*\{[^}]*display:\s*none/s',
            $css
        );
    }
}
