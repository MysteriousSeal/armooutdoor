<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A Vinted Go order's relay point, set by hand once the locker is known. */
class OrderRelayPointEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    private function order(string $slug = 'vinted-go', string $status = 'preparing', ?array $relay = null): Order
    {
        $carrier = Carrier::query()->where('slug', $slug)->firstOrFail();
        $address = ['first_name' => 'Bruno', 'last_name' => 'Lemaitre', 'line1' => '22 Square du Forsythia', 'postal_code' => '77240', 'city' => 'Cesson', 'country' => 'FR'];

        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => $address,
            'billing_address_snapshot' => $address,
            'carrier_id' => $carrier->id,
            'carrier_method' => $carrier->method,
            'carrier_snapshot' => $carrier->toSnapshot(),
            'relay_snapshot' => $relay ?? ['slug' => null, 'name' => 'Bruno Lemaitre', 'line1' => '22 Square du Forsythia', 'postal_code' => '77240', 'city' => 'Cesson', 'country' => 'FR', 'hours' => null],
            'subtotal_cents' => 450,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 450,
            'payment_method' => 'card',
        ]);
    }

    private function locker(): array
    {
        return [
            'relay_name' => 'Locker Vinted Go Intermarché',
            'relay_line1' => '1 avenue de la Gare',
            'relay_postal_code' => '77240',
            'relay_city' => 'Vert-Saint-Denis',
        ];
    }

    public function test_the_relay_point_can_be_replaced(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.relay-point.update', $order), $this->locker())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $relay = $order->refresh()->relay_snapshot;
        $this->assertSame('Locker Vinted Go Intermarché', $relay['name']);
        $this->assertSame('1 avenue de la Gare', $relay['line1']);
        $this->assertSame('77240', $relay['postal_code']);
        $this->assertSame('Vert-Saint-Denis', $relay['city']);
        $this->assertSame('FR', $relay['country']);
        // The customer's own address is untouched.
        $this->assertSame('22 Square du Forsythia', $order->address_snapshot['line1']);
        $this->assertTrue(AdminActivityLog::query()->where('action', 'order.relay_point_updated')->exists());
    }

    /** The locker is often learnt from the label, after the parcel has left. */
    public function test_it_stays_editable_once_shipped(): void
    {
        $order = $this->order(status: 'shipped');

        $html = $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.show', $order))->assertOk()->getContent();
        $this->assertStringContainsString('data-modal-open="edit-relay-point-modal"', $html);
        $this->assertStringContainsString('id="edit-relay-point-modal"', $html);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.relay-point.update', $order), $this->locker())
            ->assertSessionHasNoErrors();

        $this->assertSame('Locker Vinted Go Intermarché', $order->refresh()->relay_snapshot['name']);
    }

    public function test_an_order_with_no_relay_point_yet_can_get_one(): void
    {
        $order = $this->order();
        $order->forceFill(['relay_snapshot' => null])->save();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.show', $order))->assertSee('Add relay point');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.relay-point.update', $order), $this->locker())
            ->assertSessionHasNoErrors();

        $this->assertSame('Vert-Saint-Denis', $order->refresh()->relay_snapshot['city']);
    }

    public function test_every_field_is_required(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.relay-point.update', $order), ['relay_name' => '', 'relay_line1' => '', 'relay_postal_code' => '', 'relay_city' => ''])
            ->assertSessionHasErrorsIn('relayPoint', ['relay_name', 'relay_line1', 'relay_postal_code', 'relay_city']);

        $this->assertSame('Bruno Lemaitre', $order->refresh()->relay_snapshot['name']);
    }

    /** Only Vinted Go: another carrier's relay point comes from the customer's own choice. */
    public function test_other_carriers_do_not_offer_it(): void
    {
        $order = $this->order('mondial-relay');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.show', $order))
            ->assertDontSee('edit-relay-point-modal', false);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.relay-point.update', $order), $this->locker())
            ->assertNotFound();
    }

    public function test_a_customer_cannot_reach_it(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.orders.relay-point.update', $order), $this->locker())
            ->assertRedirect();

        $this->assertSame('Bruno Lemaitre', $order->refresh()->relay_snapshot['name']);
    }
}
