<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Remettre en rayon ce qu'un remboursement a rendu.
 *
 * Rembourser ne change que le statut de la commande — décision prise en
 * construisant le journal de stock, et qui tient toujours : la vente et le
 * retour physique sont deux événements distincts, et seul le second doit
 * faire bouger une quantité. C'est ce que ce champ, ligne par ligne, permet
 * de déclarer explicitement une fois l'article vérifié.
 */
class OrderItemRestockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(string $status = 'refunded'): Order
    {
        $customer = User::factory()->create();

        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 5000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 5500,
            'payment_method' => 'card',
        ]);
    }

    private function line(Order $order, ?Product $product, int $quantity, ?ProductVariant $variant = null): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'product_variant_id' => $variant?->id,
            'product_slug' => $product?->slug ?? 'gone',
            'name' => $product?->name ?? ['fr' => 'Article supprimé'],
            'variant_label' => $variant?->label(),
            'image' => $product?->image ?? '',
            'quantity' => $quantity,
            'unit_price_cents' => 500,
            'line_cents' => 500 * $quantity,
        ]);
    }

    public function test_restocking_increases_stock_and_logs_a_movement(): void
    {
        $product = Product::factory()->create(['quantity' => 3]);
        $order = $this->order();
        $item = $this->line($order, $product, 2);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 2])
            ->assertRedirect();

        $this->assertSame(5, $product->fresh()->quantity);

        $movement = StockMovement::query()->firstOrFail();
        $this->assertSame(StockMovementReason::OrderRefundRestock, $movement->reason);
        $this->assertSame(2, $movement->delta);
        $this->assertSame(Order::class, $movement->subject_type);
        $this->assertSame($order->id, $movement->subject_id);

        $item->refresh();
        $this->assertSame(2, $item->restocked_quantity);
        $this->assertNotNull($item->restocked_at);
        $this->assertTrue($item->isFullyRestocked());
    }

    public function test_a_line_can_be_restocked_in_more_than_one_pass(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $order = $this->order();
        $item = $this->line($order, $product, 5);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 3]);

        $this->assertSame(3, $product->fresh()->quantity);
        $this->assertSame(2, $item->fresh()->quantityRestockable());
        $this->assertFalse($item->fresh()->isFullyRestocked());

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 2]);

        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame(0, $item->fresh()->quantityRestockable());
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_restocking_a_variant_line_reconciles_the_parent(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['name' => 'Taille', 'value' => 'M']],
            'sku' => 'V-M',
            'quantity' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $product->reconcileQuantity();

        $order = $this->order();
        $item = $this->line($order, $product, 3, $variant);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 3]);

        $this->assertSame(7, $variant->fresh()->quantity);
        $this->assertSame(7, $product->fresh()->quantity);

        $movement = StockMovement::query()->firstOrFail();
        $this->assertSame($variant->id, $movement->product_variant_id);
    }

    public function test_restocking_more_than_was_sold_is_rejected(): void
    {
        $product = Product::factory()->create(['quantity' => 1]);
        $order = $this->order();
        $item = $this->line($order, $product, 2);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 3])
            ->assertSessionHasErrors('quantity_'.$item->id);

        $this->assertSame(1, $product->fresh()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_restocking_requires_a_refunded_order(): void
    {
        $product = Product::factory()->create(['quantity' => 1]);
        $order = $this->order('shipped');
        $item = $this->line($order, $product, 1);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 1])
            ->assertForbidden();

        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_a_line_whose_product_is_gone_cannot_be_restocked(): void
    {
        $order = $this->order();
        $item = $this->line($order, null, 1);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 1])
            ->assertNotFound();

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_staff_can_restock_without_being_the_owner(): void
    {
        $staff = User::factory()->admin()->create(['role' => 'staff']);
        $product = Product::factory()->create(['quantity' => 0]);
        $order = $this->order();
        $item = $this->line($order, $product, 1);

        $this->actingAs($staff)
            ->patch(route('admin.orders.items.restock', [$order, $item]), ['quantity_'.$item->id => 1])
            ->assertRedirect();

        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_the_field_only_shows_on_a_refunded_order_with_something_left_to_restock(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['quantity' => 0]);

        $shipped = $this->order('shipped');
        $this->line($shipped, $product, 1);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $shipped))
            ->assertOk()
            ->assertDontSee('order-restock-form', false);

        $refunded = $this->order();
        $item = $this->line($refunded, $product, 1);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $refunded))
            ->assertOk()
            ->assertSee('order-restock-form', false);

        $this->actingAs($admin)
            ->patch(route('admin.orders.items.restock', [$refunded, $item]), ['quantity_'.$item->id => 1]);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $refunded))
            ->assertOk()
            ->assertDontSee('order-restock-form', false)
            ->assertSee('Restocked');
    }
}
