<?php

namespace Tests\Feature\Admin;

use App\Models\Discount;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owners have full access; staff are blocked from refunds, deleting
 * discounts, viewing Stripe payment data, and managing other admins.
 */
class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    private function placedOrder(): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
        ]);
    }

    public function test_staff_cannot_refund_an_order(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $order = $this->placedOrder();

        $this->actingAs($staff)
            ->patch('/admin/orders/'.$order->number.'/refund')
            ->assertForbidden();

        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_owner_can_refund_an_order(): void
    {
        $owner = User::factory()->admin()->create();
        $order = $this->placedOrder();

        $this->actingAs($owner)
            ->patch('/admin/orders/'.$order->number.'/refund')
            ->assertRedirect();

        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_staff_cannot_delete_a_product_discount(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $product = Product::factory()->create();
        $discount = Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($staff)
            ->delete('/admin/discounts/'.$discount->id)
            ->assertForbidden();

        $this->assertDatabaseHas('discounts', ['id' => $discount->id]);
    }

    public function test_staff_cannot_delete_a_discount_code(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $code = DiscountCode::query()->create(['code' => 'STAFFTEST', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($staff)
            ->delete('/admin/discount-codes/'.$code->id)
            ->assertForbidden();

        $this->assertDatabaseHas('discount_codes', ['id' => $code->id]);
    }

    public function test_staff_cannot_view_stripe_payments_page(): void
    {
        $staff = User::factory()->staffAdmin()->create();

        $this->actingAs($staff)
            ->get('/admin/stripe/orphaned-payments')
            ->assertForbidden();
    }

    public function test_staff_cannot_see_stripe_metadata_on_an_order(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $order = $this->placedOrder();
        $order->update([
            'stripe_payment_intent_id' => 'pi_test123',
            'stripe_customer_id' => 'cus_test123',
            'payment_fee_cents' => 150,
        ]);

        $this->actingAs($staff)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertDontSee('pi_test123')
            ->assertDontSee('cus_test123')
            ->assertDontSee('Payment processing fee');
    }

    public function test_owner_can_see_stripe_metadata_on_an_order(): void
    {
        $owner = User::factory()->admin()->create();
        $order = $this->placedOrder();
        $order->update([
            'stripe_payment_intent_id' => 'pi_test123',
            'stripe_customer_id' => 'cus_test123',
            'payment_fee_cents' => 150,
        ]);

        $this->actingAs($owner)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('pi_test123')
            ->assertSee('cus_test123')
            ->assertSee('Payment processing fee');
    }

    public function test_staff_cannot_manage_admins(): void
    {
        $staff = User::factory()->staffAdmin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($staff)->get('/admin/settings/admins')->assertForbidden();
        $this->actingAs($staff)->get('/admin/settings/admins/create')->assertForbidden();
        $this->actingAs($staff)->get('/admin/settings/admins/'.$target->id.'/edit')->assertForbidden();
        $this->actingAs($staff)
            ->patch('/admin/settings/admins/'.$target->id.'/deactivate')
            ->assertForbidden();
    }

    public function test_owner_can_manage_admins(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/settings/admins')->assertOk();
    }

    public function test_the_sole_owner_cannot_demote_themselves_to_staff(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->put('/admin/settings/admins/'.$owner->id, [
                'first_name' => $owner->first_name,
                'last_name' => $owner->last_name,
                'email' => $owner->email,
                'role' => 'staff',
            ])
            ->assertSessionHas('status');

        $this->assertSame('owner', $owner->fresh()->role);
    }

    public function test_an_owner_can_be_demoted_when_another_owner_remains(): void
    {
        $owner = User::factory()->admin()->create();
        $otherOwner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->put('/admin/settings/admins/'.$otherOwner->id, [
                'first_name' => $otherOwner->first_name,
                'last_name' => $otherOwner->last_name,
                'email' => $otherOwner->email,
                'role' => 'staff',
            ])
            ->assertRedirect('/admin/settings/admins');

        $this->assertSame('staff', $otherOwner->fresh()->role);
    }
}
