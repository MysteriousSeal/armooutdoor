<?php

namespace Tests\Feature\Admin;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\PdfImageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le bon de commande fournisseur en PDF. */
class PurchaseOrderPdfTest extends TestCase
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
            'sku' => 'MECH-MPACT-WOOD',
            'supplier_reference' => 'DM-4471',
            'quantity_ordered' => 6,
            'quantity_received' => 4,
            'unit_cost_cents' => 2218,
        ]);

        return $order->fresh(['items']);
    }

    private function download(PurchaseOrder $order): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/purchase-orders/'.$order->number.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->streamedContent();
    }

    public function test_the_pdf_downloads_under_its_order_number(): void
    {
        $order = $this->purchaseOrder();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/purchase-orders/'.$order->number.'/pdf')
            ->assertOk()
            ->assertDownload('bc-'.$order->number.'.pdf');
    }

    public function test_the_page_offers_the_download(): void
    {
        $order = $this->purchaseOrder();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/purchase-orders/'.$order->number)
            ->assertOk()
            ->assertSee('/admin/purchase-orders/'.$order->number.'/pdf', false)
            ->assertSee('Purchase order PDF');
    }

    public function test_the_document_carries_what_was_ordered(): void
    {
        $html = $this->render($this->purchaseOrder());

        $this->assertStringContainsString('Bon de commande', $html);
        $this->assertStringContainsString('BC-20260823-LQMA', $html);
        $this->assertStringContainsString('DM Diffusion', $html);
        $this->assertStringContainsString('DM-4471', $html);
        $this->assertStringContainsString('Gants M-Pact Woodland', $html);
        // 6 × 22,18 € = 133,08 € HT, port 9 €, TVA 20 % : 170,52 € TTC.
        // La TVA se calcule par ligne, comme partout ailleurs sur le bon.
        $this->assertStringContainsString('133,08', $html);
        $this->assertStringContainsString('170,52', $html);
    }

    public function test_the_document_never_says_what_was_received(): void
    {
        $order = $this->purchaseOrder();
        $html = $this->render($order);

        // Le fournisseur lit ce qu'on lui commande. L'état de nos réceptions
        // bougera après l'envoi, et n'a rien à faire sur le document.
        $this->assertStringNotContainsString('Reçu', $html);
        $this->assertStringNotContainsString('reçue', $html);
        $this->assertStringContainsString('>6<', str_replace([' ', "\n"], ['', ''], $html));
        $this->assertSame(4, $order->items->first()->quantity_received);
    }

    private function render(PurchaseOrder $order): string
    {
        return view('admin.purchase-orders.pdf', [
            'purchaseOrder' => $order->load(['supplier', 'items.product', 'items.variant']),
            'company' => CompanySetting::current(),
        ])->render();
    }

    public function test_a_line_shows_the_product_image(): void
    {
        $image = 'products/'.basename((string) glob(public_path('images/products/*.webp'))[0]);
        $order = $this->purchaseOrder();
        $order->items->first()->product->update(['image' => $image]);

        $html = $this->render($order->fresh(['items.product']));

        preg_match('/<img src="([^"]+)"/', $html, $match);

        // Un chemin disque, pas une URL : le générateur de PDF lit le fichier.
        $this->assertNotEmpty($match, 'Aucune image sur la ligne.');
        $this->assertFileExists($match[1]);
        // Et une image réduite pour l'impression, pas la photo de 1000 px, qui
        // coûtait une demi-seconde de génération à elle seule.
        [$width] = getimagesize($match[1]);
        $this->assertLessThanOrEqual(PdfImageCache::SIZE, $width);
    }

    public function test_a_missing_image_leaves_the_cell_empty(): void
    {
        $order = $this->purchaseOrder();
        $order->items->first()->product->update(['image' => 'products/parti-en-fumee.webp']);

        // Une image absente ne doit pas casser le document.
        $this->assertStringNotContainsString('<img', $this->render($order->fresh(['items.product'])));
    }

    public function test_the_name_keeps_the_line_to_itself(): void
    {
        $html = $this->render($this->purchaseOrder());

        // La variante et la référence vivent sous le nom, pas dans leurs
        // propres colonnes : la désignation garde toute la largeur restante.
        $this->assertStringNotContainsString('col-variant', $html);
        $this->assertStringNotContainsString('col-sku', $html);
        $this->assertStringContainsString('line-detail', $html);
        $this->assertStringContainsString('DM-4471', $html);
    }

    public function test_the_sender_block_holds_two_columns(): void
    {
        $html = $this->render($this->purchaseOrder());

        // Adresse d'un côté, moyens de joindre de l'autre : trois lignes au
        // lieu de six en haut de page.
        $this->assertStringContainsString('company-cols', $html);
        $this->assertStringContainsString('SIRET', $html);
        $this->assertStringContainsString(CompanySetting::current()->value('contact_email'), $html);
    }
}
