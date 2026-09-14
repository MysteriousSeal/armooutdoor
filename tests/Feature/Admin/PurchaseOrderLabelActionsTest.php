<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Once a purchase order is fully received, each line offers the same label
 * actions Catalog › Labels does: edit the wording, and download the sheet
 * when the article has everything a label needs.
 */
class PurchaseOrderLabelActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $line
     */
    private function receivedOrder(array $product = [], array $line = [], array $wording = []): PurchaseOrder
    {
        $article = Product::factory()->labelled($wording)->create(array_merge([
            'name' => ['en' => 'Tactical gloves', 'fr' => 'Gants tactiques'],
            'sku' => 'ARM-GLOVE-M',
            'gtin' => '4006381333931',
        ], $product));

        $po = PurchaseOrder::factory()->create([
            'status' => 'received',
            'sent_at' => now(),
            'received_at' => now(),
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $article->id,
            'product_variant_id' => $line['variant']->id ?? null,
            'name' => $article->localizedName(),
            'sku' => $line['sku'] ?? $article->sku,
            'quantity_ordered' => 5,
            'quantity_received' => 5,
            'unit_cost_cents' => 100,
        ]);

        return $po->fresh(['items.product.label', 'items.variant']);
    }

    private function page(PurchaseOrder $po): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.purchase-orders.show', $po))
            ->assertOk()
            ->getContent();
    }

    public function test_a_fully_received_order_offers_edit_and_download_on_each_line(): void
    {
        $po = $this->receivedOrder();
        $item = $po->items->first();
        $html = $this->page($po);

        $this->assertStringContainsString('Edit label', $html);
        $this->assertStringContainsString('href="'.e($item->labelEditUrl()).'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(e($item->labelEditUrl()), '/').'"[^>]*target="_blank"/',
            $html,
        );
        $this->assertStringContainsString('Download label', $html);
        $this->assertStringContainsString('href="'.e($item->labelDownloadUrl()).'"', $html);
        $this->assertStringContainsString('po-label-download', $html);
        $this->assertSame([], $item->missingLabelRequirements());
        $this->assertStringNotContainsString('GTIN set', $html);
        $this->assertStringNotContainsString('label-requirements-missing', $html);
        $this->assertSame(
            route('admin.labels.index', ['search' => 'ARM-GLOVE-M']),
            $item->labelEditUrl(),
        );
        $this->assertSame(
            route('admin.products.label', $item->product),
            $item->labelDownloadUrl(),
        );
    }

    public function test_edit_label_filters_the_labels_page_on_the_product(): void
    {
        $po = $this->receivedOrder();
        $item = $po->items->first();

        $this->actingAs(User::factory()->admin()->create())
            ->get($item->labelEditUrl())
            ->assertOk()
            ->assertSee('Gants tactiques')
            ->assertSee('ARM-GLOVE-M');
    }

    public function test_download_stays_off_when_the_article_is_short_of_a_label_requirement(): void
    {
        $po = $this->receivedOrder(['gtin' => null], wording: ['subtitle' => null]);
        $item = $po->items->first();
        $html = $this->page($po);

        $this->assertNull($item->labelDownloadUrl());
        $this->assertSame(['Subtitle', 'GTIN'], $item->missingLabelRequirements());
        $this->assertStringContainsString('Edit label', $html);
        $this->assertStringContainsString('is-disabled', $html);
        $this->assertStringNotContainsString('po-label-download', $html);
        $this->assertStringContainsString('Missing Subtitle', $html);
        $this->assertStringContainsString('Missing GTIN', $html);
        $this->assertStringNotContainsString('Missing Title', $html);
        $this->assertStringNotContainsString('Missing SKU', $html);
        $this->assertStringNotContainsString('GTIN set', $html);
        $this->assertStringNotContainsString(
            '<a href="'.route('admin.products.label', $item->product).'"',
            $html,
        );
    }

    public function test_a_variant_line_downloads_that_size_sheet(): void
    {
        $product = Product::factory()->labelled()->create([
            'name' => ['en' => 'Breathable tee', 'fr' => 'T-shirt respirant'],
            'sku' => null,
            'gtin' => null,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'attribute_values' => [['label' => 'Size', 'value' => 'M']],
            'sku' => 'ARM-TS-M',
            'gtin' => '4006381333931',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $po = PurchaseOrder::factory()->create([
            'status' => 'received',
            'sent_at' => now(),
            'received_at' => now(),
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => $product->localizedName(),
            'sku' => $variant->sku,
            'quantity_ordered' => 3,
            'quantity_received' => 3,
            'unit_cost_cents' => 100,
        ]);
        $po = $po->fresh(['items.product.label', 'items.variant']);
        $item = $po->items->first();

        $html = $this->page($po);

        $this->assertSame(
            route('admin.labels.index', ['search' => 'T-shirt respirant']),
            $item->labelEditUrl(),
        );
        $this->assertSame(
            route('admin.products.variants.label', ['product' => $product, 'variant' => $variant]),
            $item->labelDownloadUrl(),
        );
        $this->assertStringContainsString('href="'.e($item->labelDownloadUrl()).'"', $html);
        $this->assertSame([], $item->missingLabelRequirements());
        $this->assertStringNotContainsString('label-requirements-missing', $html);
        $this->assertStringNotContainsString('GTIN set', $html);
    }

    public function test_a_variant_line_lists_its_own_missing_codes_not_the_product_ones(): void
    {
        $product = Product::factory()->labelled()->create([
            'name' => ['en' => 'Breathable tee', 'fr' => 'T-shirt respirant'],
            'sku' => 'ARM-TS',
            'gtin' => '4006381333931',
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'L', 'fr' => 'L'],
            'attribute_values' => [['label' => 'Size', 'value' => 'L']],
            'sku' => 'ARM-TS-L',
            'gtin' => null,
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $po = PurchaseOrder::factory()->create([
            'status' => 'received',
            'sent_at' => now(),
            'received_at' => now(),
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => $product->localizedName(),
            'sku' => $variant->sku,
            'quantity_ordered' => 3,
            'quantity_received' => 3,
            'unit_cost_cents' => 100,
        ]);
        $item = $po->fresh(['items.product.label', 'items.variant'])->items->first();

        $html = $this->page($po);

        $this->assertSame(['GTIN'], $item->missingLabelRequirements());
        $this->assertTrue(filled($product->gtin));
        $this->assertStringContainsString('Missing GTIN', $html);
        $this->assertStringNotContainsString('Missing SKU', $html);
        $this->assertStringNotContainsString('GTIN set', $html);
    }

    public function test_an_open_order_does_not_offer_the_buttons(): void
    {
        $product = Product::factory()->labelled()->create([
            'sku' => 'ARM-OPEN',
            'gtin' => '4006381333931',
        ]);
        $po = PurchaseOrder::factory()->sent()->create();
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'sku' => $product->sku,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_cost_cents' => 100,
        ]);

        $html = $this->page($po->fresh('items'));

        $this->assertStringNotContainsString('Edit label', $html);
        $this->assertStringNotContainsString('Download label', $html);
        $this->assertStringNotContainsString('po-label-actions', $html);
    }

    public function test_a_partially_received_order_does_not_offer_the_buttons(): void
    {
        $product = Product::factory()->labelled()->create([
            'sku' => 'ARM-PARTIAL',
            'gtin' => '4006381333931',
        ]);
        $po = PurchaseOrder::factory()->create([
            'status' => 'partially_received',
            'sent_at' => now(),
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'sku' => $product->sku,
            'quantity_ordered' => 5,
            'quantity_received' => 5,
            'unit_cost_cents' => 100,
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'sku' => $product->sku,
            'quantity_ordered' => 3,
            'quantity_received' => 1,
            'unit_cost_cents' => 100,
        ]);

        $html = $this->page($po->fresh('items'));

        $this->assertStringNotContainsString('Edit label', $html);
        $this->assertStringNotContainsString('Download label', $html);
    }

    public function test_a_deleted_product_keeps_the_column_without_the_buttons(): void
    {
        $po = $this->receivedOrder();
        $po->items->first()->product->delete();

        $html = $this->page($po->fresh(['items.product', 'items.variant']));

        $this->assertStringContainsString('po-label-cell', $html);
        $this->assertStringNotContainsString('Edit label', $html);
        $this->assertStringNotContainsString('Download label', $html);
        $this->assertStringNotContainsString('GTIN set', $html);
        $this->assertStringNotContainsString('label-requirements-missing', $html);
        $this->assertStringContainsString('product deleted', $html);
    }
}
