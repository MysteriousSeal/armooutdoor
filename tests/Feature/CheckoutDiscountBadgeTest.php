<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reduction badge on a checkout line: a percentage, the same figure the
 * cart showed a step earlier.
 */
class CheckoutDiscountBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
    }

    private function checkoutWithDiscount(string $type, int $value, int $priceCents = 1690): string
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5, 'price_cents' => $priceCents]);

        Discount::query()->create(['product_id' => $product->id, 'type' => $type, 'value' => $value]);

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        return $this->actingAs($user)->get('/checkout')->assertOk()->getContent();
    }

    public function test_a_euro_discount_badge_reads_as_a_percentage(): void
    {
        // 2 € off 16,90 €.
        $html = $this->checkoutWithDiscount('fixed', 200);

        $this->assertStringContainsString('<span class="badge badge-active cart-line-discount-badge">-11%</span>', $html);
        $this->assertStringNotContainsString('cart-line-discount-badge">-2,00', $html);
    }

    public function test_a_percentage_badge_is_unchanged(): void
    {
        $html = $this->checkoutWithDiscount('percentage', 20);

        $this->assertStringContainsString('<span class="badge badge-active cart-line-discount-badge">-20%</span>', $html);
    }
}
