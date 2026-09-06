<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three product strips on the home page skip « Rupture de stock ».
 *
 * A restocking or supplier-available piece is not that chip, and still
 * belongs on the page.
 */
class HomeOutOfStockBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, int $quantity = 20, array $extra = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'name' => ['fr' => $name],
            'price_cents' => 2000,
            'quantity' => $quantity,
            'category_id' => Category::factory()->create()->id,
        ], $extra));
    }

    private function discount(Product $product): Discount
    {
        return Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 20,
        ]);
    }

    public function test_an_out_of_stock_offer_does_not_appear_in_the_deals_strip(): void
    {
        $this->discount($this->product('Cible en rupture', 0));
        $this->discount($this->product('Cible en promotion', 8));

        preg_match(
            '#<section class="home-deals".*?</section>#s',
            $this->get('/')->assertOk()->getContent(),
            $block,
        );

        $this->assertNotEmpty($block);
        $this->assertStringContainsString('Cible en promotion', $block[0]);
        $this->assertStringNotContainsString('Cible en rupture', $block[0]);
    }

    public function test_only_out_of_stock_offers_means_no_deals_block(): void
    {
        $this->discount($this->product('Cible en rupture', 0));

        $this->get('/')
            ->assertOk()
            ->assertDontSee('home-deals-row', false)
            ->assertDontSee('Cible en rupture');
    }

    public function test_featured_skips_an_out_of_stock_product_for_an_in_stock_one(): void
    {
        $category = Category::factory()->create();
        $this->product('Cible en rupture', 0, [
            'category_id' => $category->id,
            'image' => 'products/ridge-tent.jpg',
            'description' => ['fr' => str_repeat('Texte. ', 40)],
        ]);
        $this->product('Cible en stock', 12, [
            'category_id' => $category->id,
            'image' => '',
            'description' => ['fr' => 'Court.'],
        ]);

        preg_match(
            '#<section class="home-featured".*?</section>#s',
            $this->get('/')->assertOk()->getContent(),
            $block,
        );

        $this->assertNotEmpty($block);
        $this->assertStringContainsString('Cible en stock', $block[0]);
        $this->assertStringNotContainsString('Cible en rupture', $block[0]);
    }

    public function test_more_does_not_fill_with_out_of_stock_products(): void
    {
        $category = Category::factory()->create();
        foreach (range(1, 12) as $i) {
            $this->product('Cible en stock '.$i, 10, ['category_id' => $category->id]);
        }
        $this->product('Cible en rupture', 0, ['category_id' => $category->id, 'sort_order' => -1]);

        preg_match(
            '#<section class="home-more".*?</section>#s',
            $this->get('/')->assertOk()->getContent(),
            $block,
        );

        $this->assertNotEmpty($block);
        $this->assertStringNotContainsString('Cible en rupture', $block[0]);
        $this->assertStringNotContainsString(__('store.out_of_stock'), $block[0]);
    }

    public function test_a_restocking_product_still_appears(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $product = $this->product('Cible en réassort', 0, [
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

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Cible en réassort', $html);
        $this->assertStringNotContainsString(__('store.out_of_stock'), $html);
    }

    public function test_a_supplier_available_product_still_appears(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $this->product('Cible chez le fournisseur', 0, [
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Cible chez le fournisseur', $html);
        $this->assertStringNotContainsString(__('store.out_of_stock'), $html);
    }
}
