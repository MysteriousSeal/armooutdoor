<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPreparing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The "being prepared" email: one per move into preparation, to the same
 * audience the confirmation writes to, and never twice.
 */
class OrderPreparingEmailTest extends TestCase
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

    public function test_marking_an_order_as_preparing_emails_the_customer(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $order = $this->order($customer);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/orders/'.$order->number.'/prepare')
            ->assertRedirect();

        Notification::assertSentTo($customer, OrderPreparing::class, function (OrderPreparing $notification) use ($customer, $order): bool {
            $html = (string) $notification->toMail($customer)->render();

            return str_contains($html, $order->number)
                && str_contains($html, 'en cours de')
                && str_contains($html, 'Suivre ma commande');
        });
    }

    public function test_re_marking_a_preparing_order_does_not_resend(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $order = $this->order($customer, ['status' => 'preparing']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/orders/'.$order->number.'/prepare')
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

        $this->actingAs($admin)->patch('/admin/orders/'.$marketplaceOrder->number.'/prepare')->assertRedirect();
        $this->actingAs($admin)->patch('/admin/orders/'.$externalOrder->number.'/prepare')->assertRedirect();

        Notification::assertNothingSent();
    }
}
