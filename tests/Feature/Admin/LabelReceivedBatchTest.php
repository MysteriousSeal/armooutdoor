<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The second download on the labels page: the same label, with the day the
 * article last came in from a purchase order as its batch.
 */
class LabelReceivedBatchTest extends TestCase
{
    use RefreshDatabase;

    private function ready(array $overrides = []): Product
    {
        return Product::factory()
            ->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])
            ->create(array_merge(['sku' => 'ARM-GLOVE', 'gtin' => '4006381333931'], $overrides));
    }

    private function received(Product $product, string $on, ?ProductVariant $variant = null, StockMovementReason $reason = StockMovementReason::PurchaseOrderReceived, int $delta = 5): void
    {
        $this->travelTo(Carbon::parse($on.' 14:00'));

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'reason' => $reason,
            'delta' => $delta,
        ]);

        $this->travelBack();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** The drawn text of a PDF; the renderer writes two bytes per character. */
    private function drawn(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        return str_replace("\0", '', collect($matches[1])->map(fn (string $stream): string => (string) @gzuncompress($stream))->join("\n"));
    }

    public function test_the_label_carries_the_last_day_the_article_was_received(): void
    {
        $product = $this->ready();
        $this->received($product, '2026-09-03');
        $this->received($product, '2026-10-12');
        // A sale, or any other movement, is not a receipt.
        $this->received($product, '2026-10-20', reason: StockMovementReason::OrderPlaced, delta: -1);

        $pdf = $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/label?batch=received')
            ->assertOk()
            ->getContent();

        $drawn = $this->drawn($pdf);
        $this->assertStringContainsString('2026-10-12', $drawn);
        $this->assertStringNotContainsString(now()->format('Y-m-d'), $drawn);
    }

    public function test_a_variant_takes_its_own_receipts_only(): void
    {
        $product = $this->ready(['sku' => null, 'gtin' => null]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Size', 'value' => 'M']],
            'sku' => 'ARM-GLOVE-M',
            'gtin' => '4006381333931',
            'quantity' => 1,
            'is_active' => true,
        ]);
        $other = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Size', 'value' => 'L']],
            'sku' => 'ARM-GLOVE-L',
            'gtin' => '4006381333948',
            'quantity' => 1,
            'is_active' => true,
        ]);
        $this->received($product, '2026-08-01', $variant);
        $this->received($product, '2026-10-01', $other);

        $pdf = $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/variants/'.$variant->id.'/label?batch=received')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('2026-08-01', $this->drawn($pdf));
    }

    public function test_an_article_never_received_has_no_such_label(): void
    {
        $product = $this->ready();

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/label?batch=received')
            ->assertNotFound();
    }

    public function test_the_list_offers_the_received_batch_beside_the_usual_button(): void
    {
        $product = $this->ready();
        $this->received($product, '2026-10-12');

        $this->actingAs($this->admin())->get('/admin/labels')
            ->assertOk()
            ->assertSee('Download label')
            ->assertSee('<a href="'.route('admin.products.label', $product).'?batch=received"', false)
            ->assertSee('Batch 2026-10-12');
    }

    public function test_the_received_batch_is_greyed_for_an_article_never_received(): void
    {
        $product = $this->ready();

        $this->actingAs($this->admin())->get('/admin/labels')
            ->assertOk()
            ->assertSee('<a href="'.route('admin.products.label', $product).'"', false)
            ->assertSee('title="Never received from a purchase order">Batch received</span>', false)
            ->assertDontSee('?batch=received', false);
    }
}
