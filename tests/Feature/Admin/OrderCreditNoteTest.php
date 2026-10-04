<?php

namespace Tests\Feature\Admin;

use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A refunded order has two documents: the invoice of the sale, as billed,
 * and the credit note that gives it back, dated the day of the refund.
 */
class OrderCreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        $this->travelTo('2026-09-01 10:00:00');

        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 4000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 4500,
            'payment_method' => 'card',
            ...$attributes,
        ]);
    }

    private function refunded(): Order
    {
        $order = $this->order();
        $this->travelTo('2026-10-04 11:00:00');
        $order->markStatus('refunded');

        return $order->refresh();
    }

    /** The sheet's HTML, before the renderer turns it into a PDF. */
    private function render(Order $order, array $data): string
    {
        return view('admin.orders.invoice-pdf', [
            'order' => $order->load('items', 'statusHistories'),
            'company' => CompanySetting::current(),
            ...$data,
        ])->render();
    }

    public function test_the_credit_note_gives_the_sale_back_on_the_refund_date(): void
    {
        $order = $this->refunded();

        $html = $this->render($order, ['creditNote' => true]);

        $this->assertStringContainsString('Remboursement', $html);
        $this->assertStringContainsString('RFD-'.$order->number.' · 04/10/2026', $html);
        $this->assertStringContainsString('Remboursement de la facture INV-'.$order->number.' du 01/09/2026', $html);
        $this->assertStringContainsString('Total remboursé TTC', $html);
        $this->assertStringContainsString('-45,00', $html);
    }

    public function test_the_admin_invoice_shows_the_sale_as_billed(): void
    {
        $order = $this->refunded();

        $html = $this->render($order, ['netRefund' => false]);

        $this->assertStringContainsString('INV-'.$order->number.' · 01/09/2026', $html);
        $this->assertStringNotContainsString('Remboursement', $html);
        $this->assertStringContainsString('45,00', $html);
    }

    public function test_the_customer_invoice_still_nets_the_refund(): void
    {
        $order = $this->refunded();

        $html = $this->render($order, []);

        $this->assertStringContainsString('Remboursement', $html);
    }

    public function test_the_order_page_offers_both_documents_once_refunded(): void
    {
        $order = $this->refunded();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(route('admin.orders.invoice', $order), false)
            ->assertSee(route('admin.orders.credit-note', $order), false);
    }

    public function test_both_documents_download(): void
    {
        $order = $this->refunded();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.orders.invoice', $order))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.orders.credit-note', $order))
            ->assertOk()
            ->assertDownload('remboursement-'.$order->number.'.pdf');
    }

    public function test_an_order_not_refunded_has_no_credit_note(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.credit-note', $order))
            ->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.show', $order))
            ->assertDontSee(route('admin.orders.credit-note', $order), false);
    }
}
