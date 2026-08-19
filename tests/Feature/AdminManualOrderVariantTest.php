<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManualOrderVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class, AdminSeeder::class]);
    }

    private function shippingFields(): array
    {
        return [
            'first_name' => 'Jean',
            'last_name' => 'Client',
            'line1' => '1 rue du Test',
            'postal_code' => '75000',
            'city' => 'Paris',
            'country' => 'FR',
            'billing_first_name' => 'Jean',
            'billing_last_name' => 'Client',
            'billing_line1' => '1 rue du Test',
            'billing_postal_code' => '75000',
            'billing_city' => 'Paris',
            'billing_country' => 'FR',
        ];
    }

    public function test_manual_order_requires_a_variant_when_the_product_has_variants(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille M']],
            'quantity' => 5,
            'is_active' => true,
        ]);
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/orders', [
                'action' => 'placed',
                'customer_mode' => 'existing',
                'customer_id' => $customer->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'carrier_id' => $carrier->id,
                ...$this->shippingFields(),
            ])
            ->assertSessionHasErrors('items.0.variant_id');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_admin_can_create_a_manual_order_for_a_specific_variant(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille M']],
            'price_cents' => 39900,
            'quantity' => 5,
            'is_active' => true,
        ]);
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/orders', [
                'action' => 'placed',
                'customer_mode' => 'existing',
                'customer_id' => $customer->id,
                'items' => [
                    ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2],
                ],
                'carrier_id' => $carrier->id,
                ...$this->shippingFields(),
            ])
            ->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame('Taille M', $item->variant_label);
        $this->assertSame(79800, $item->line_cents);
        $this->assertSame(3, $variant->fresh()->quantity);
        $this->assertSame(3, $product->fresh()->quantity, 'product quantity must mirror the sum of its variants, not stay frozen');
    }
}
