<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Valider un brouillon depuis la fiche de commande.
 *
 * Le formulaire d'édition savait déjà le faire, mais il fallait rouvrir toute
 * la commande pour appuyer sur « enregistrer et finaliser ». Le bouton fait la
 * même chose en un clic : le statut passe à « passée » et le stock part.
 */
class OrderValidateDraftTest extends TestCase
{
    use RefreshDatabase;

    private function draft(int $quantity = 1, int $stock = 4): Order
    {
        $product = Product::factory()->create(['quantity' => $stock]);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'draft',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'relay',
            'carrier_snapshot' => ['name' => ['fr' => 'Chronopost']],
            'subtotal_cents' => 999 * $quantity,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 999 * $quantity,
            'payment_method' => 'card',
            'is_manual' => true,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => $product->name,
            'image' => '',
            'unit_price_cents' => 999,
            'quantity' => $quantity,
            'line_cents' => 999 * $quantity,
        ]);

        return $order;
    }

    public function test_a_draft_becomes_a_placed_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();

        $this->actingAs($admin)
            ->patch(route('admin.orders.validate-draft', $order))
            ->assertRedirect();

        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_the_stock_is_taken(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft(quantity: 3, stock: 10);
        $product = $order->items->first()->product;

        $this->actingAs($admin)->patch(route('admin.orders.validate-draft', $order));

        // Un brouillon ne réserve rien : c'est la validation qui prend le stock.
        $this->assertSame(7, $product->fresh()->quantity);
    }

    public function test_short_stock_does_not_block_it(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft(quantity: 5, stock: 2);
        $product = $order->items->first()->product;

        // La vente a eu lieu sur la place de marché quoi qu'en dise l'étagère :
        // refuser ici empêcherait seulement de l'enregistrer.
        $this->actingAs($admin)
            ->patch(route('admin.orders.validate-draft', $order))
            ->assertRedirect();

        $this->assertSame('placed', $order->fresh()->status);
        $this->assertSame(-3, $product->fresh()->quantity);
    }

    public function test_the_status_history_starts(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();

        $this->actingAs($admin)->patch(route('admin.orders.validate-draft', $order));

        $this->assertTrue($order->fresh()->statusHistories->contains('status', 'placed'));
    }

    public function test_it_is_recorded_in_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();

        $this->actingAs($admin)->patch(route('admin.orders.validate-draft', $order));

        $this->assertTrue(AdminActivityLog::query()->where('action', 'order.draft_validated')->exists());
    }

    public function test_an_order_that_is_not_a_draft_cannot_be_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();
        $order->update(['status' => 'placed']);
        $product = $order->items->first()->product;

        // Sinon le bouton servirait à reprendre du stock une deuxième fois.
        $this->actingAs($admin)
            ->patch(route('admin.orders.validate-draft', $order))
            ->assertNotFound();

        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_validating_twice_takes_the_stock_once(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft(quantity: 2, stock: 10);
        $product = $order->items->first()->product;

        $this->actingAs($admin)->patch(route('admin.orders.validate-draft', $order));
        $this->actingAs($admin)->patch(route('admin.orders.validate-draft', $order));

        $this->assertSame(8, $product->fresh()->quantity);
    }

    public function test_the_button_and_its_modal_are_on_a_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('Validate draft')
            ->assertSee('validate-draft-modal', false)
            ->assertSee('data-draft-validate', false);
    }

    public function test_the_button_ships_hidden(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();

        // Sans JavaScript la modale ne s'ouvre pas : un bouton mort vaut moins
        // que pas de bouton, le formulaire d'édition reste la voie de secours.
        $html = $this->actingAs($admin)->get('/admin/orders/'.$order->number)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-draft-validate\s+hidden>/', $html);
        $this->assertStringContainsString('js/admin-draft-validate.js', $html);
    }

    public function test_edit_draft_stays_available_beside_it(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('Edit draft')
            ->assertSee(route('admin.orders.edit', $order), false);
    }

    public function test_a_real_order_shows_no_such_button(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draft();
        $order->update(['status' => 'placed']);

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertDontSee('Validate draft');
    }
}
