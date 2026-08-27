<?php

namespace Tests\Feature\Admin;

use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The delivery slip that goes in the parcel.
 *
 * It is the one document the customer reads before the invoice, and the only
 * one printed while packing: it has to name what is in the box and where it
 * goes, and it must never carry a price — the slip is what an admin hands to
 * a relay point or slips into a gift.
 */
class OrderDeliverySlipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(array $overrides = []): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'preparing',
            'address_snapshot' => [
                'first_name' => 'Julien', 'last_name' => 'Marchand', 'line1' => '4 rue des Lilas',
                'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR',
            ],
            'billing_address_snapshot' => [
                'first_name' => 'Julien', 'last_name' => 'Marchand', 'line1' => '4 rue des Lilas',
                'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR',
            ],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 4990,
            'shipping_cents' => 590,
            'discount_cents' => 0,
            'total_cents' => 5580,
            'payment_method' => 'card',
            ...$overrides,
        ]);

        $product = Product::factory()->create(['sku' => 'CAG-MCFOREST-BREATH']);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => ['fr' => 'Cagoule McForest', 'en' => 'McForest hood'],
            'image' => '',
            'quantity' => 2,
            'unit_price_cents' => 2495,
            'line_cents' => 4990,
        ]);

        return $order->fresh();
    }

    private function render(Order $order): string
    {
        return view('admin.orders.delivery-slip-pdf', [
            'order' => $order->load('items.product', 'items.variant'),
            'company' => CompanySetting::current(),
        ])->render();
    }

    public function test_the_slip_downloads_under_the_order_number(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.delivery-slip', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('bdl-'.$order->number.'.pdf');
    }

    public function test_a_draft_has_nothing_to_ship_yet(): void
    {
        $order = $this->order(['status' => 'draft']);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.delivery-slip', $order))
            ->assertNotFound();
    }

    public function test_the_order_page_offers_the_slip_once_the_order_is_real(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Download delivery slip');
    }

    public function test_the_order_page_does_not_offer_a_slip_for_a_draft(): void
    {
        $order = $this->order(['status' => 'draft']);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Download delivery slip');
    }

    public function test_the_document_names_the_order_the_recipient_and_the_carrier(): void
    {
        $order = $this->order();
        $html = $this->render($order);

        $this->assertStringContainsString('Bon de livraison', $html);
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('Julien', $html);
        $this->assertStringContainsString('4 rue des Lilas', $html);
        $this->assertStringContainsString('31000', $html);
        $this->assertStringContainsString('Colissimo', $html);
    }

    public function test_the_document_lists_what_is_in_the_parcel(): void
    {
        $html = $this->render($this->order());

        $this->assertStringContainsString('Cagoule McForest', $html);
        $this->assertStringContainsString('CAG-MCFOREST-BREATH', $html);
        // Two of the one line: the packer counts pieces, not rows.
        $this->assertStringContainsString('× 2', $html);
    }

    public function test_the_document_never_carries_a_price(): void
    {
        // What the customer paid is the invoice's business. A slip with an
        // amount on it cannot be used for a gift or handed to a relay point.
        $html = $this->render($this->order());

        $this->assertStringNotContainsString('€', $html);
        $this->assertStringNotContainsString('49,90', $html);
        $this->assertStringNotContainsString('55,80', $html);
    }

    public function test_a_relay_delivery_prints_the_relay_address_not_the_home_one(): void
    {
        $order = $this->order([
            'carrier_method' => 'relay',
            'relay_snapshot' => [
                'name' => 'Tabac de la Poste',
                'line1' => '12 place du Capitole',
                'postal_code' => '31000',
                'city' => 'Toulouse',
            ],
        ]);

        $html = $this->render($order);

        $this->assertStringContainsString('Tabac de la Poste', $html);
        $this->assertStringContainsString('12 place du Capitole', $html);
        $this->assertStringNotContainsString('4 rue des Lilas', $html);
    }
}
