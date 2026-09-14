<?php

namespace Tests\Feature\Api;

use App\Models\Discount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product discounts API: list, create and edit the sale prices the web
 * admin shows under /admin/discounts?tab=products, with the same rules.
 */
class AdminDiscountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'test-admin-api-token']);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-admin-api-token'];
    }

    private function discount(array $attributes = []): Discount
    {
        return Discount::query()->create($attributes + [
            'product_id' => Product::factory()->create(['price_cents' => 5000])->id,
            'type' => 'percentage',
            'value' => 10,
        ]);
    }

    public function test_requests_without_the_token_are_rejected(): void
    {
        $discount = $this->discount();

        $this->getJson('/api/admin/discounts')->assertUnauthorized();
        $this->postJson('/api/admin/discounts', [])->assertUnauthorized();
        $this->getJson('/api/admin/discounts/'.$discount->id)->assertUnauthorized();
        $this->patchJson('/api/admin/discounts/'.$discount->id, [])->assertUnauthorized();
    }

    public function test_a_percentage_discount_can_be_created(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000]);

        $this->postJson('/api/admin/discounts', [
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 20,
        ], $this->headers())->assertCreated()
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonPath('data.value', 20)
            ->assertJsonPath('data.label', '-20%')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.product.discounted_price_cents', 4000);

        $this->assertDatabaseHas('discounts', ['product_id' => $product->id, 'value' => 20]);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'discount.created']);
    }

    public function test_a_fixed_discount_is_sent_in_euros_and_stored_in_cents(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000]);

        $this->postJson('/api/admin/discounts', [
            'product_id' => $product->id,
            'type' => 'fixed',
            'value' => 12.5,
            'starts_at' => now()->addDay()->toIso8601String(),
        ], $this->headers())->assertCreated()
            ->assertJsonPath('data.value', 12.5)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.product.discounted_price_cents', 3750);

        $this->assertDatabaseHas('discounts', ['product_id' => $product->id, 'value' => 1250]);
    }

    public function test_creation_requires_product_type_and_value(): void
    {
        $this->postJson('/api/admin/discounts', [], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'type', 'value']);
    }

    public function test_a_product_cannot_have_two_discounts(): void
    {
        $existing = $this->discount();

        $this->postJson('/api/admin/discounts', [
            'product_id' => $existing->product_id,
            'type' => 'fixed',
            'value' => 5,
        ], $this->headers())->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_a_percentage_above_100_is_refused(): void
    {
        $this->postJson('/api/admin/discounts', [
            'product_id' => Product::factory()->create()->id,
            'type' => 'percentage',
            'value' => 150,
        ], $this->headers())->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_a_discount_can_be_edited_field_by_field(): void
    {
        $discount = $this->discount(['ends_at' => now()->addWeek()]);

        $this->patchJson('/api/admin/discounts/'.$discount->id, [
            'value' => 25,
        ], $this->headers())->assertOk()
            ->assertJsonPath('data.value', 25)
            // Absent fields keep their value.
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonPath('data.ends_at', $discount->ends_at->toIso8601String());

        $this->patchJson('/api/admin/discounts/'.$discount->id, [
            'ends_at' => null,
        ], $this->headers())->assertOk()
            ->assertJsonPath('data.ends_at', null)
            ->assertJsonPath('data.value', 25);
    }

    public function test_changing_the_type_requires_the_value_again(): void
    {
        $discount = $this->discount();

        $this->patchJson('/api/admin/discounts/'.$discount->id, [
            'type' => 'fixed',
        ], $this->headers())->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);

        $this->patchJson('/api/admin/discounts/'.$discount->id, [
            'type' => 'fixed',
            'value' => 7.99,
        ], $this->headers())->assertOk()
            ->assertJsonPath('data.value', 7.99);

        $this->assertSame(799, $discount->fresh()->value);
    }

    public function test_the_end_date_cannot_precede_the_stored_start_date(): void
    {
        $discount = $this->discount(['starts_at' => now()->addWeek()]);

        $this->patchJson('/api/admin/discounts/'.$discount->id, [
            'ends_at' => now()->addDay()->toIso8601String(),
        ], $this->headers())->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_products_list_stock_and_average_paid_incl_vat(): void
    {
        $product = Product::factory()->create(['quantity' => 4]);
        $untouched = Product::factory()->create(['quantity' => 0]);
        $discount = $this->discount(['product_id' => $product->id]);

        $order = PurchaseOrder::factory()->create([
            'vat_rate_basis_points' => 2000,
            'additional_costs_cents' => 0,
            'discount_cents' => 0,
        ]);

        foreach ([[4, 4, 150], [2, 2, 200], [5, 0, 999]] as [$ordered, $received, $cost]) {
            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $order->id,
                'product_id' => $product->id,
                'name' => 'Line',
                'quantity_ordered' => $ordered,
                'quantity_received' => $received,
                'unit_cost_cents' => $cost,
            ]);
        }

        $rows = collect($this->getJson('/api/admin/discounts/products', $this->headers())
            ->assertOk()
            ->json('data'))
            ->keyBy('id');

        // (4 × 1.50 + 2 × 2.00) excl. VAT, +20 %, over 6 units; the unreceived line is ignored.
        $this->assertSame(200, $rows[$product->id]['average_paid_incl_vat_cents']);
        $this->assertSame(6, $rows[$product->id]['received_units']);
        $this->assertSame(4, $rows[$product->id]['quantity']);
        $this->assertSame($discount->id, $rows[$product->id]['discount_id']);

        $this->assertNull($rows[$untouched->id]['average_paid_incl_vat_cents']);
        $this->assertSame(0, $rows[$untouched->id]['received_units']);
        $this->assertNull($rows[$untouched->id]['discount_id']);
    }

    public function test_the_list_filters_by_status_and_product(): void
    {
        $active = $this->discount();
        $scheduled = $this->discount(['starts_at' => now()->addDay()]);
        $expired = $this->discount(['ends_at' => now()->subDay()]);

        $this->getJson('/api/admin/discounts?status=active', $this->headers())->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);

        $this->getJson('/api/admin/discounts?status=expired', $this->headers())->assertOk()
            ->assertJsonPath('data.0.id', $expired->id);

        $this->getJson('/api/admin/discounts?product_id='.$scheduled->product_id, $this->headers())->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'scheduled');

        $this->getJson('/api/admin/discounts?status=bogus', $this->headers())->assertUnprocessable();
    }
}
