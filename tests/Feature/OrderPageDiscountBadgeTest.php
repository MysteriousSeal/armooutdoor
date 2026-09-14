<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reduction badge on a placed order: a percentage, the figure the
 * customer saw in the cart, worked out from the prices the order kept so it
 * holds for orders placed before the change too.
 */
class OrderPageDiscountBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function page(int $originalCents, int $unitCents, string $label): string
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => $unitCents, 'shipping_cents' => 0, 'discount_cents' => 0, 'total_cents' => $unitCents,
            'payment_method' => 'card',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => $product->name,
            'image' => '',
            'unit_price_cents' => $unitCents,
            'original_unit_price_cents' => $originalCents,
            'discount_label' => $label,
            'quantity' => 1,
            'line_cents' => $unitCents,
        ]);

        return $this->actingAs($user)->get('/orders/'.$order->number)->assertOk()->getContent();
    }

    public function test_a_euro_discount_badge_reads_as_a_percentage(): void
    {
        // 2 € off 16,90 €, saved as « -2,00 € » at checkout.
        $html = $this->page(1690, 1490, '-2,00 €');

        $this->assertStringContainsString('<span class="order-discount-badge">-11%</span>', $html);
        $this->assertStringNotContainsString('<span class="order-discount-badge">-2,00 €</span>', $html);
    }

    public function test_a_saved_percentage_is_kept_rather_than_recomputed(): void
    {
        // 15% off 10,01 € rounds to 1,50 € off, which recomputed would read 14%.
        $html = $this->page(1001, 851, '-15%');

        $this->assertStringContainsString('<span class="order-discount-badge">-15%</span>', $html);
        $this->assertStringNotContainsString('<span class="order-discount-badge">-14%</span>', $html);
    }

    public function test_a_discount_under_one_percent_keeps_its_amount(): void
    {
        $html = $this->page(30000, 29999, '-0,01 €');

        $this->assertStringContainsString('<span class="order-discount-badge">-0,01 €</span>', $html);
    }
}
