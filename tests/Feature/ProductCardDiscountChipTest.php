<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reduction chip on a product card, wherever a grid of cards is shown.
 */
class ProductCardDiscountChipTest extends TestCase
{
    use RefreshDatabase;

    private function onSale(string $type, int $value, int $priceCents): Product
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5, 'price_cents' => $priceCents]);

        Discount::query()->create(['product_id' => $product->id, 'type' => $type, 'value' => $value]);

        return $product;
    }

    public function test_a_euro_discount_is_shown_as_a_percentage(): void
    {
        // 2 € off 16,90 €.
        $this->onSale('fixed', 200, 1690);

        $this->get(localized_route('products.promotions'))
            ->assertOk()
            ->assertSee('<span class="card-discount-chip">-11%</span>', false)
            ->assertDontSee('<span class="card-discount-chip">-2,00', false);
    }

    public function test_a_percentage_discount_is_unchanged(): void
    {
        $this->onSale('percentage', 20, 1690);

        $this->get(localized_route('products.promotions'))
            ->assertOk()
            ->assertSee('<span class="card-discount-chip">-20%</span>', false);
    }

    public function test_the_same_card_reads_the_same_on_the_home_page(): void
    {
        $this->onSale('fixed', 200, 1690);

        $this->get('/')
            ->assertOk()
            ->assertSee('<span class="card-discount-chip">-11%</span>', false);
    }
}
