<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 70 × 50 mm address label a Lettre suivie envelope wears.
 *
 * A letter has no parcel to slip a delivery slip into, so the label is the
 * one document that travels with it: the recipient, and nothing else — no
 * order number, no phone, no price. It exists only for orders that actually
 * ship by letter; every other carrier prints its own label.
 */
class OrderAddressLabelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(array $overrides = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'preparing',
            'address_snapshot' => [
                'first_name' => 'Julien', 'last_name' => 'Marchand', 'line1' => '4 rue des Lilas',
                'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR', 'phone' => '0612345678',
            ],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['slug' => 'lettre-suivie', 'name' => ['fr' => 'Lettre suivie']],
            'subtotal_cents' => 990,
            'shipping_cents' => 350,
            'discount_cents' => 0,
            'total_cents' => 1340,
            'payment_method' => 'card',
            ...$overrides,
        ]);
    }

    private function render(Order $order): string
    {
        return view('admin.orders.address-label-pdf', ['order' => $order])->render();
    }

    public function test_the_label_downloads_under_the_order_number(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.address-label', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('adresse-'.$order->number.'.pdf');
    }

    public function test_only_a_lettre_suivie_order_has_a_label(): void
    {
        $order = $this->order(['carrier_snapshot' => ['slug' => 'colissimo', 'name' => ['fr' => 'Colissimo']]]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.address-label', $order))
            ->assertNotFound();
    }

    public function test_a_draft_has_no_envelope_to_address_yet(): void
    {
        $order = $this->order(['status' => 'draft']);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.address-label', $order))
            ->assertNotFound();
    }

    public function test_the_order_page_offers_the_label_to_the_right_orders_only(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $this->order()))
            ->assertOk()
            ->assertSee('Download address label');

        $parcel = $this->order(['carrier_snapshot' => ['slug' => 'colissimo', 'name' => ['fr' => 'Colissimo']]]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $parcel))
            ->assertOk()
            ->assertDontSee('Download address label');
    }

    public function test_the_label_carries_the_recipient_and_nothing_else(): void
    {
        $order = $this->order();
        $html = $this->render($order);

        $this->assertStringContainsString('Julien', $html);
        $this->assertStringContainsString('4 rue des Lilas', $html);
        $this->assertStringContainsString('31000 Toulouse', $html);
        $this->assertStringContainsString('France', $html);
        // Recipient only: no order number, no phone.
        $this->assertStringNotContainsString($order->number, $html);
        $this->assertStringNotContainsString('0612345678', $html);
    }

    public function test_a_foreign_address_names_its_country(): void
    {
        $order = $this->order([
            'address_snapshot' => [
                'first_name' => 'Lena', 'last_name' => 'Peeters', 'line1' => '8 rue Haute',
                'postal_code' => '1000', 'city' => 'Bruxelles', 'country' => 'BE',
            ],
        ]);

        $this->assertStringContainsString('Belgique', $this->render($order));
    }
}
