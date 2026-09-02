<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Pertinence » used to be « Nouveautés » wearing a different label. It
 * is business sense now: what can be bought leads, then what sells, then
 * what gets looked at, then the hand-ranking.
 */
class CategoryRelevanceSortTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, string $name, int $quantity, int $sortOrder = 0): Product
    {
        return Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['fr' => $name],
            'quantity' => $quantity,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function sell(Product $product, int $quantity): void
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0, 'total_cents' => 1000,
            'payment_method' => 'card',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
            'name' => ['fr' => 'X'], 'image' => '', 'quantity' => $quantity,
            'unit_price_cents' => 1000, 'line_cents' => 1000 * $quantity,
        ]);
    }

    private function recordViews(Product $product, int $times): void
    {
        foreach (range(1, $times) as $i) {
            SiteVisit::create(['path' => '/products/'.$product->slug, 'product_id' => $product->id]);
        }
    }

    public function test_relevance_ranks_availability_then_sales_then_views_then_hand_order(): void
    {
        $category = Category::factory()->create();

        $nothing = $this->product($category, 'Rien', 10, 1);
        $sells = $this->product($category, 'Vend', 10, 9);
        $seen = $this->product($category, 'Regardé', 10, 9);
        $soldOut = $this->product($category, 'Épuisé', 0, 0);

        $this->sell($sells, 3);
        $this->sell($soldOut, 99); // Best seller, but nothing to buy.
        $this->recordViews($seen, 5);

        $names = $this->get('/categories/'.$category->slug.'?sort=relevance')
            ->assertOk()
            ->viewData('products')
            ->map(fn (Product $product) => $product->localizedName())
            ->all();

        // Buyable first; among them sales beat views beat the hand-ranking;
        // the sold-out best seller sinks to the bottom.
        $this->assertSame(['Vend', 'Regardé', 'Rien', 'Épuisé'], $names);
    }

    public function test_a_refunded_sale_recommends_nothing(): void
    {
        $category = Category::factory()->create();

        $plain = $this->product($category, 'Simple', 10, 0);
        $refunded = $this->product($category, 'Remboursé', 10, 5);

        $this->sell($refunded, 10);
        Order::query()->latest('id')->first()->update(['status' => 'refunded']);

        $names = $this->get('/categories/'.$category->slug.'?sort=relevance')
            ->assertOk()
            ->viewData('products')
            ->map(fn (Product $product) => $product->localizedName())
            ->all();

        $this->assertSame(['Simple', 'Remboursé'], $names);
    }
}
