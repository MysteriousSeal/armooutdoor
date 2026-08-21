<?php

namespace Tests\Feature\Admin;

use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La mention imprimée au bas d'une facture de place de marché.
 *
 * Elle est figée sur la commande à sa création, pour qu'une reformulation
 * ultérieure ne réécrive pas des factures déjà émises. Une commande créée
 * avant que la plateforme ait sa mention n'en a pourtant aucune : sa facture
 * partirait alors sans la mention légale sur les frais encaissés.
 */
class InvoiceNoteTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'shipped',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 1000,
            'payment_method' => 'card',
            'is_manual' => true,
            ...$attributes,
        ]);
    }

    public function test_the_stored_note_is_used_when_there_is_one(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'Vinted', 'note' => 'Mention actuelle']);
        $order = $this->order([
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'Vinted',
            'marketplace_note' => 'Mention du jour de la vente',
        ]);

        // Une facture déjà émise ne doit pas changer de texte parce que la
        // mention de la plateforme a été reformulée depuis.
        $this->assertSame('Mention du jour de la vente', $order->invoiceNote());
    }

    public function test_an_order_without_a_stored_note_falls_back_to_the_marketplace(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'LeBonCoin', 'note' => 'Mention LeBonCoin']);
        $order = $this->order([
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'LeBonCoin',
            'marketplace_note' => null,
        ]);

        // Le cas réel : la commande est arrivée avant que la mention soit
        // configurée, et sa facture partirait sans rien.
        $this->assertSame('Mention LeBonCoin', $order->invoiceNote());
    }

    public function test_an_empty_stored_note_also_falls_back(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'Ebay', 'note' => 'Mention Ebay']);
        $order = $this->order([
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'Ebay',
            'marketplace_note' => '',
        ]);

        $this->assertSame('Mention Ebay', $order->invoiceNote());
    }

    public function test_no_note_anywhere_prints_nothing(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'NaturaBuy', 'note' => null]);
        $order = $this->order([
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'NaturaBuy',
            'marketplace_note' => null,
        ]);

        $this->assertNull($order->invoiceNote());
    }

    public function test_a_shop_order_has_no_marketplace_note(): void
    {
        $this->assertNull($this->order(['is_manual' => false])->invoiceNote());
    }

    public function test_the_invoice_prints_the_fallback(): void
    {
        $admin = User::factory()->admin()->create();
        $marketplace = Marketplace::query()->create(['name' => 'LeBonCoin', 'note' => 'Frais encaissés par la plateforme.']);
        $order = $this->order([
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'LeBonCoin',
            'marketplace_note' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.invoice', $order))
            ->assertOk();
    }
}
