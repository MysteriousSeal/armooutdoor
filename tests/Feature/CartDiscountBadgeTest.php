<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reduction badge on a cart line: a percentage, the same figure the
 * product card and the product page showed before it was added.
 */
class CartDiscountBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function addOnSale(string $type, int $value, int $priceCents = 1690): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5, 'price_cents' => $priceCents]);

        Discount::query()->create(['product_id' => $product->id, 'type' => $type, 'value' => $value]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_a_euro_discount_badge_reads_as_a_percentage(): void
    {
        // 2 € off 16,90 €.
        $this->addOnSale('fixed', 200);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('<span class="badge badge-active cart-line-discount-badge">-11%</span>', false)
            ->assertDontSee('cart-line-discount-badge">-2,00', false);
    }

    public function test_a_percentage_badge_is_unchanged(): void
    {
        $this->addOnSale('percentage', 20);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('<span class="badge badge-active cart-line-discount-badge">-20%</span>', false);
    }
}
