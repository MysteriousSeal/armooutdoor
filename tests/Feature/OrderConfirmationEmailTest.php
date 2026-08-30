<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The order confirmation email: sent when an order is really placed — from
 * the shop or typed in for a real customer — silent for marketplace orders
 * and external shadow accounts, and honest about a pending payment.
 */
class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
    }

    private function placeOrder(User $user): Order
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $carrier->id,
            'payment_method' => 'paypal',
        ]);

        return Order::query()->firstOrFail();
    }

    public function test_placing_a_shop_order_emails_a_confirmation(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $order = $this->placeOrder($user);

        Notification::assertSentTo($user, OrderConfirmed::class, function (OrderConfirmed $notification) use ($user, $order): bool {
            $html = (string) $notification->toMail($user)->render();

            return str_contains($html, $order->number)
                && str_contains($html, 'Poêle en fonte')
                && str_contains($html, format_euros($order->total_cents))
                && str_contains($html, 'Paiement en attente');
        });
    }

    public function test_a_card_order_email_carries_no_pending_payment_note(): void
    {
        $user = User::factory()->create();
        $order = $this->placeOrder($user);
        $order->forceFill(['payment_method' => 'card'])->save();

        $html = (string) (new OrderConfirmed($order->fresh()))->toMail($user)->render();

        $this->assertStringNotContainsString('Paiement en attente', $html);
        $this->assertStringContainsString('Suivre ma commande', $html);
    }

    /** @return array<string, mixed> */
    private function manualOrderPayload(User $customer): array
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        return [
            'action' => 'placed',
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'carrier_id' => $carrier->id,
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

    public function test_a_manual_order_for_a_real_customer_emails_the_confirmation(): void
    {
        Notification::fake();
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/orders', $this->manualOrderPayload($customer))
            ->assertRedirect();

        Notification::assertSentTo($customer, OrderConfirmed::class);
    }

    public function test_a_manual_marketplace_order_stays_silent(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $marketplace = \App\Models\Marketplace::query()->create(['name' => 'Naturabuy']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/orders', [
                ...$this->manualOrderPayload($customer),
                'marketplace_id' => $marketplace->id,
            ])
            ->assertRedirect();

        // Silencieux pour le client — la boutique elle-même, prévenue par
        // AdminOrderPlaced, n'est pas le client.
        Notification::assertSentTimes(OrderConfirmed::class, 0);
    }

    public function test_validating_a_draft_emails_the_confirmation(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/orders', [...$this->manualOrderPayload($customer), 'action' => 'draft'])
            ->assertRedirect();

        $order = Order::query()->sole();
        Notification::assertNothingSent();

        $this->actingAs($admin)
            ->patch('/admin/orders/'.$order->number.'/validate-draft')
            ->assertRedirect();

        Notification::assertSentTo($customer, OrderConfirmed::class);
    }

    public function test_a_manual_order_for_a_new_external_customer_sends_nothing(): void
    {
        Notification::fake();

        // Typed in with a fresh name and email: the account created behind it
        // is an external shadow, and its address was never verified — nothing
        // may be mailed there, marketplace or not.
        $payload = $this->manualOrderPayload(User::factory()->create());
        unset($payload['customer_id']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/orders', [
                ...$payload,
                'customer_mode' => 'new',
                'new_customer_first_name' => 'Marc',
                'new_customer_last_name' => 'Externe',
                'new_customer_email' => 'marc.externe@example.com',
            ])
            ->assertRedirect();

        $this->assertTrue(Order::query()->sole()->user->external);
        // Rien pour le client fantôme ; la boutique, elle, est prévenue.
        Notification::assertSentTimes(OrderConfirmed::class, 0);
    }

    public function test_a_manual_order_without_an_account_sends_nothing(): void
    {
        Notification::fake();

        // The shape of a marketplace or hand-typed order: an "external"
        // shadow account stands in for the customer.
        $external = User::factory()->create(['external' => true]);
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $external->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 1000,
            'payment_method' => 'card',
        ]);

        $order->sendConfirmationEmail();
        $this->app->terminate();

        Notification::assertNothingSent();
    }
}
