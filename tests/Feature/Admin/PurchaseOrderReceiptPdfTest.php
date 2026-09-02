<?php

namespace Tests\Feature\Admin;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le bon de réception : la feuille qu'on coche en ouvrant les colis. */
class PurchaseOrderReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseOrder(int $received = 4): PurchaseOrder
    {
        $supplier = Supplier::query()->create(['name' => 'DM Diffusion', 'lead_time_days' => 4]);

        $order = PurchaseOrder::query()->create([
            'number' => 'BC-20260823-LQMA',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'status' => 'sent',
            'shipping_cents' => 900,
            'discount_cents' => 0,
            'additional_costs_cents' => 0,
            'vat_rate_basis_points' => 2000,
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'Gants M-Pact Woodland',
            'sku' => 'MECH-MPACT-WOOD',
            'supplier_reference' => 'DM-4471',
            'quantity_ordered' => 6,
            'quantity_received' => $received,
            'unit_cost_cents' => 2218,
        ]);

        return $order->fresh(['items']);
    }

    private function render(PurchaseOrder $order): string
    {
        return view('admin.purchase-orders.receipt-pdf', [
            'purchaseOrder' => $order->load(['supplier', 'items.product', 'items.variant']),
            'company' => CompanySetting::current(),
        ])->render();
    }

    public function test_the_sheet_downloads_under_its_own_name(): void
    {
        $order = $this->purchaseOrder();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/purchase-orders/'.$order->number.'/receipt-pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('br-'.$order->number.'.pdf');
    }

    public function test_the_page_offers_both_documents(): void
    {
        $order = $this->purchaseOrder();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/purchase-orders/'.$order->number)
            ->assertOk()
            ->assertSee('Purchase order PDF')
            ->assertSee('Receipt sheet PDF')
            ->assertSee('/admin/purchase-orders/'.$order->number.'/receipt-pdf', false);
    }

    public function test_the_lines_carry_two_boxes_to_tick(): void
    {
        $html = $this->render($this->purchaseOrder());

        $this->assertStringContainsString('Bon de réception', $html);
        $this->assertStringContainsString('Received', $html);
        $this->assertStringContainsString('Handled', $html);
        // Une case par colonne à cocher, et une pour écrire le compte trouvé.
        $this->assertSame(2, substr_count($html, 'class="check-box"'));
        $this->assertSame(1, substr_count($html, 'class="count-box"'));
    }

    public function test_the_boxes_are_empty_even_when_stock_was_already_received(): void
    {
        // Cocher d'avance inviterait à croire le papier plutôt que le carton.
        $html = $this->render($this->purchaseOrder(received: 6));

        $this->assertStringContainsString('<div class="check-box"></div>', $html);
        $this->assertStringNotContainsString('is-checked', $html);
    }

    public function test_the_sheet_carries_no_money(): void
    {
        $html = $this->render($this->purchaseOrder());

        // Ni prix ni totaux : ce n'est pas ce qu'on vérifie une caisse ouverte.
        $this->assertStringNotContainsString('€', $html);
        $this->assertStringNotContainsString('22,18', $html);
        $this->assertStringNotContainsString('Total', $html);
        $this->assertStringNotContainsString('TVA', $html);
    }

    public function test_the_line_says_what_was_ordered_and_which_article(): void
    {
        $html = $this->render($this->purchaseOrder());

        $this->assertStringContainsString('Gants M-Pact Woodland', $html);
        // The shop's own SKU: the sheet is read next to the shop's shelves.
        $this->assertStringContainsString('MECH-MPACT-WOOD', $html);
        $this->assertStringNotContainsString('DM-4471', $html);
        $this->assertStringContainsString('>6<', str_replace([' ', "\n"], '', $html));
    }

    public function test_the_sheet_can_be_signed_and_dated(): void
    {
        $html = $this->render($this->purchaseOrder());

        // Une réception sans nom ni date ne prouve rien un mois plus tard.
        $this->assertStringContainsString('Reçu le', $html);
        $this->assertStringContainsString('signoff-line', $html);
    }
}
