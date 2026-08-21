<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coûts saisis à zéro contre coûts jamais renseignés, dans la liste des
 * commandes.
 *
 * « 0,00 € » veut dire « vérifié, rien à déduire ». Un tiret veut dire « pas
 * encore renseigné ». Les afficher pareil efface le travail déjà fait, et rien
 * n'indique plus quelles commandes restent à traiter.
 */
class OrderRecordedCostsTest extends TestCase
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
            'marketplace_commission_cents' => null,
            'shipping_paid_cents' => null,
            'payment_fee_cents' => null,
            ...$attributes,
        ]);
    }

    public function test_nothing_recorded_means_no_recorded_costs(): void
    {
        $this->assertFalse($this->order()->hasRecordedCosts());
    }

    public function test_a_zero_counts_as_recorded(): void
    {
        // Le cœur du sujet : zéro est une valeur, pas une absence.
        $this->assertTrue($this->order(['marketplace_commission_cents' => 0])->hasRecordedCosts());
        $this->assertTrue($this->order(['shipping_paid_cents' => 0])->hasRecordedCosts());
        $this->assertTrue($this->order(['payment_fee_cents' => 0])->hasRecordedCosts());
    }

    public function test_one_field_filled_is_enough(): void
    {
        $this->assertTrue($this->order(['shipping_paid_cents' => 450])->hasRecordedCosts());
    }

    public function test_costs_recorded_at_zero_show_a_figure_not_a_dash(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order([
            'marketplace_commission_cents' => 0,
            'shipping_paid_cents' => 0,
        ]);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('stripe-fee-chip', false)
            ->assertSee(format_euros(0));
    }

    public function test_an_order_with_nothing_recorded_still_shows_a_dash(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order();

        $html = $this->actingAs($admin)->get('/admin/orders')->assertOk()->getContent();

        // Le tiret reste le signal « à traiter ».
        $this->assertStringContainsString('—', $html);
        $this->assertStringNotContainsString('stripe-fee-chip', $html);
    }

    public function test_a_commission_at_zero_is_listed_among_the_deductions(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order(['marketplace_commission_cents' => 0]);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('title="Commission"', false);
    }

    public function test_a_field_left_empty_is_not_listed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order(['marketplace_commission_cents' => 0, 'shipping_paid_cents' => null]);

        // Commission saisie, port non : seule la première doit apparaître.
        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('title="Commission"', false)
            ->assertDontSee('title="Shipping paid"', false);
    }

    public function test_the_perceived_total_is_unaffected(): void
    {
        // Zéro et null se valent pour le calcul : seul l'affichage change.
        $this->assertSame(1000, $this->order()->perceivedTotalCents());
        $this->assertSame(1000, $this->order(['marketplace_commission_cents' => 0])->perceivedTotalCents());
    }
}
