<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminOrderPlaced;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La boutique est prévenue de chaque commande devenue réelle — passée au
 * checkout comme saisie à la main — à l'adresse configurée. Adresse vide :
 * silence.
 */
class AdminOrderPlacedEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);
        config()->set('shop.order_notification_email', 'store.swift.shelf@gmail.com');
    }

    private function placeShopOrder(): Order
    {
        $user = User::factory()->create();
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

    public function test_a_shop_order_notifies_the_configured_address_with_the_full_detail(): void
    {
        Notification::fake();

        $order = $this->placeShopOrder();

        Notification::assertSentOnDemand(AdminOrderPlaced::class, function (AdminOrderPlaced $notification, array $channels, AnonymousNotifiable $notifiable) use ($order): bool {
            $html = (string) $notification->toMail($notifiable)->render();

            return $notifiable->routes['mail'] === 'store.swift.shelf@gmail.com'
                && str_contains($html, $order->number)
                && str_contains($html, 'Site')
                && str_contains($html, 'Poêle en fonte')
                && str_contains($html, route('admin.orders.show', $order));
        });
    }

    public function test_a_manual_order_notifies_too_and_says_so(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();

        $this->actingAs($admin)->post('/admin/orders', [
            'action' => 'placed',
            'customer_mode' => 'new',
            'new_customer_first_name' => 'Jean',
            'new_customer_last_name' => 'Martin',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => '9.99']],
            'carrier_id' => Carrier::query()->where('slug', 'colissimo-home')->value('id'),
            'shipping_price' => '5.00',
            'first_name' => 'Jean', 'last_name' => 'Martin',
            'line1' => '1 rue Test', 'line2' => '',
            'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR', 'phone' => '',
            'billing_first_name' => 'Jean', 'billing_last_name' => 'Martin',
            'billing_line1' => '1 rue Test', 'billing_line2' => '',
            'billing_postal_code' => '75001', 'billing_city' => 'Paris', 'billing_country' => 'FR', 'billing_phone' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(Order::query()->where('status', '!=', 'draft')->first());

        Notification::assertSentOnDemand(AdminOrderPlaced::class, function (AdminOrderPlaced $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return str_contains((string) $notification->toMail($notifiable)->render(), 'Commande manuelle');
        });
    }

    public function test_a_blank_address_sends_nothing(): void
    {
        Notification::fake();
        config()->set('shop.order_notification_email', null);

        $this->placeShopOrder();

        Notification::assertNotSentTo(new AnonymousNotifiable, AdminOrderPlaced::class);
    }
}
