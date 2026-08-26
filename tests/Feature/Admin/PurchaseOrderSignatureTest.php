<?php

namespace Tests\Feature\Admin;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les deux documents se signent, côté ArmoOutdoor. */
class PurchaseOrderSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseOrder(): PurchaseOrder
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
            'quantity_ordered' => 6,
            'quantity_received' => 0,
            'unit_cost_cents' => 2218,
        ]);

        return $order->fresh(['items']);
    }

    /** @return array<int, string> */
    private function bothDocuments(): array
    {
        $order = $this->purchaseOrder()->load(['supplier', 'items.product', 'items.variant']);
        $company = CompanySetting::current();

        return [
            'bon de commande' => view('admin.purchase-orders.pdf', compact('order', 'company') + ['purchaseOrder' => $order])->render(),
            'bon de réception' => view('admin.purchase-orders.receipt-pdf', compact('company') + ['purchaseOrder' => $order])->render(),
        ];
    }

    public function test_both_documents_end_with_a_signature_block(): void
    {
        foreach ($this->bothDocuments() as $document => $html) {
            $this->assertStringContainsString('class="signature"', $html, $document);
            $this->assertStringContainsString('Pour ArmoOutdoor', $html, $document);
            $this->assertStringContainsString('sign-rule', $html, $document);
        }
    }

    public function test_the_block_asks_for_a_name_a_date_and_a_signature(): void
    {
        foreach ($this->bothDocuments() as $document => $html) {
            $this->assertStringContainsString('Nom', $html, $document);
            $this->assertStringContainsString('Date', $html, $document);
            // Deux champs courts — le nom et la date — sans compter la
            // déclaration de style qui porte la même classe.
            $this->assertSame(2, substr_count($html, '<div class="sign-rule-short"></div>'), $document);
        }
    }

    public function test_nothing_is_filled_in_for_the_signer(): void
    {
        foreach ($this->bothDocuments() as $document => $html) {
            // Le papier ne doit affirmer la signature de personne : les
            // trois champs partent vides.
            $this->assertStringContainsString('<div class="sign-rule"></div>', $html, $document);
            $this->assertStringContainsString('<div class="sign-rule-short"></div>', $html, $document);
        }
    }

    public function test_the_signature_sits_at_the_end_before_the_footer(): void
    {
        foreach ($this->bothDocuments() as $document => $html) {
            $this->assertLessThan(
                strpos($html, '<div class="footer">'),
                strpos($html, 'class="signature"'),
                $document
            );
        }
    }

    public function test_the_receipt_sheet_keeps_its_own_unpacking_strip(): void
    {
        $html = $this->bothDocuments()['bon de réception'];

        // Qui a ouvert les colis et quand, puis la signature : deux moments,
        // deux marques.
        $this->assertStringContainsString('Reçu le', $html);
        $this->assertLessThan(
            strpos($html, 'class="signature"'),
            strpos($html, 'signoff-line'),
        );
    }
}
