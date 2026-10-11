<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderShipped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The "shipped" email: one per move to shipped, with the tracking when there
 * is one, to the same audience the confirmation writes to, and never twice.
 */
class OrderShippedEmailTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer, array $overrides = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'Jean', 'last_name' => 'Client', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'Jean', 'last_name' => 'Client', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 1000,
            'payment_method' => 'card',
            ...$overrides,
        ]);
    }

    public function test_marking_an_order_as_shipped_emails_the_customer(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $order = $this->order($customer, ['status' => 'preparing', 'tracking_number' => '6A12345678901']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/orders/'.$order->number.'/ship')
            ->assertRedirect();

        Notification::assertSentTo($customer, OrderShipped::class, function (OrderShipped $notification) use ($customer, $order): bool {
            $html = (string) $notification->toMail($customer)->render();

            return str_contains($html, $order->number)
                && str_contains($html, 'expédiée avec Colissimo')
                && str_contains($html, '6A12345678901')
                && str_contains($html, 'Suivre ma commande');
        });
    }

    public function test_re_marking_a_shipped_order_does_not_resend(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $order = $this->order($customer, ['status' => 'shipped']);
        $order->statusHistories()->create(['status' => 'shipped']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/orders/'.$order->number.'/ship')
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_marketplace_and_external_orders_stay_silent(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $marketplace = Marketplace::query()->create(['name' => 'Naturabuy']);
        $marketplaceOrder = $this->order(User::factory()->create(), ['marketplace_id' => $marketplace->id]);
        $externalOrder = $this->order(User::factory()->create(['external' => true]));

        $this->actingAs($admin)->patch('/admin/orders/'.$marketplaceOrder->number.'/ship')->assertRedirect();
        $this->actingAs($admin)->patch('/admin/orders/'.$externalOrder->number.'/ship')->assertRedirect();

        Notification::assertNothingSent();
    }
}
