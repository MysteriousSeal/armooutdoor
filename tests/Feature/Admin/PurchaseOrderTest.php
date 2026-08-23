<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le cycle de vie d'un bon de commande fournisseur.
 *
 * Brouillon modifiable, envoi qui fige les lignes, annulation et suppression
 * réservées au propriétaire. Le stock ne bouge jamais ici : il ne monte qu'à
 * la réception, testée séparément.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'DM Diffusion', 'lead_time_days' => 4]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Supplier $supplier, Product $product, array $overrides = []): array
    {
        return [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'cost' => '3.50'],
            ],
            ...$overrides,
        ];
    }

    public function test_a_draft_is_created_with_a_number_and_snapshots(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['supplier_id' => $supplier->id, 'supplier_price_cents' => 350]);

        $this->actingAs($admin)
            ->post('/admin/purchase-orders', $this->payload($supplier, $product))
            ->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^BC-\d{8}-[A-Z0-9]{4}$/', $po->number);
        $this->assertSame('draft', $po->status);
        $this->assertSame('DM Diffusion', $po->supplier_name);
        $this->assertSame($admin->id, $po->created_by_user_id);
        $this->assertSame(350, $po->items->first()->unit_cost_cents);
        $this->assertSame($product->localizedName(), $po->items->first()->name);
    }

    public function test_the_cost_prefills_from_the_supplier_price_when_left_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['supplier_price_cents' => 1234]);

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'cost' => '']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1234, PurchaseOrder::query()->firstOrFail()->items->first()->unit_cost_cents);
    }

    public function test_an_order_without_lines_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/purchase-orders', ['supplier_id' => $this->supplier()->id, 'items' => []])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_creating_moves_no_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['quantity' => 3]);

        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));

        // Commander n'est pas recevoir.
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_a_draft_can_be_edited(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.purchase-orders.update', $po), [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 9, 'cost' => '2.00']],
        ])->assertSessionHasNoErrors();

        $item = $po->fresh()->items->first();
        $this->assertSame(9, $item->quantity_ordered);
        $this->assertSame(200, $item->unit_cost_cents);
    }

    public function test_sending_locks_the_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.purchase-orders.send', $po))->assertRedirect();

        $po->refresh();
        $this->assertSame('sent', $po->status);
        $this->assertNotNull($po->sent_at);

        // Plus modifiable : le bon est parti chez le fournisseur.
        $this->actingAs($admin)->get(route('admin.purchase-orders.edit', $po))->assertNotFound();
        $this->actingAs($admin)->put(route('admin.purchase-orders.update', $po), $this->payload($supplier, $product))->assertNotFound();
    }

    public function test_staff_can_create_and_send_but_not_cancel_or_delete(): void
    {
        $staff = User::factory()->admin()->create(['role' => 'staff']);
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($staff)->post('/admin/purchase-orders', $this->payload($supplier, $product))->assertSessionHasNoErrors();
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($staff)->delete(route('admin.purchase-orders.destroy', $po))->assertForbidden();

        $this->actingAs($staff)->patch(route('admin.purchase-orders.send', $po))->assertRedirect();
        $this->actingAs($staff)->patch(route('admin.purchase-orders.cancel', $po))->assertForbidden();

        $this->assertSame('sent', $po->fresh()->status);
    }

    public function test_the_owner_can_cancel_a_sent_order(): void
    {
        $owner = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($owner)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($owner)->patch(route('admin.purchase-orders.send', $po));

        $this->actingAs($owner)->patch(route('admin.purchase-orders.cancel', $po))->assertRedirect();

        $po->refresh();
        $this->assertSame('cancelled', $po->status);
        $this->assertNotNull($po->cancelled_at);
    }

    public function test_only_a_draft_can_be_deleted(): void
    {
        $owner = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($owner)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($owner)->patch(route('admin.purchase-orders.send', $po));

        // Un bon envoyé est la trace de quelque chose qui a eu lieu.
        $this->actingAs($owner)->delete(route('admin.purchase-orders.destroy', $po))->assertForbidden();
        $this->assertSame(1, PurchaseOrder::query()->count());
    }

    public function test_a_deleted_supplier_leaves_the_order_readable(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));

        $supplier->delete();

        $po = PurchaseOrder::query()->firstOrFail();
        $this->assertNull($po->supplier_id);
        $this->assertSame('DM Diffusion', $po->supplier_name);
        $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk()->assertSee('DM Diffusion');
    }

    public function test_the_lifecycle_is_written_to_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.purchase-orders.send', $po));
        $this->actingAs($admin)->patch(route('admin.purchase-orders.cancel', $po));

        foreach (['purchase_order.created', 'purchase_order.sent', 'purchase_order.cancelled'] as $action) {
            $this->assertTrue(AdminActivityLog::query()->where('action', $action)->exists(), $action);
        }
    }

    public function test_the_index_lists_and_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/purchase-orders?tab=draft')
            ->assertOk()
            ->assertSee($po->number)
            ->assertSee('DM Diffusion');
    }

    public function test_the_nav_badge_counts_orders_awaiting_receipt(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        // Un brouillon n'attend rien : pas de pastille.
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertDontSee('awaiting receipt');

        $this->actingAs($admin)->patch(route('admin.purchase-orders.send', $po));

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('1 awaiting receipt');
    }

    public function test_a_price_typed_incl_vat_is_stored_excl_vat(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        // 4,20 € TTC à 20 % : la ligne garde 3,50 € HT. Le taux n'est qu'une
        // aide de saisie, rien de la TVA n'est conservé sur le bon.
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'shipping_price' => '12.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '4.20']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->firstOrFail();
        $this->assertSame(350, $po->items->first()->unit_cost_cents);
        $this->assertSame(1000, $po->shipping_cents);
    }

    public function test_each_french_rate_converts_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();

        foreach (['0' => 1000, '5.5' => 948, '10' => 909, '20' => 833] as $rate => $expected) {
            $product = Product::factory()->create();

            $this->actingAs($admin)->post('/admin/purchase-orders', [
                'supplier_id' => $supplier->id,
                'vat_rate' => (string) $rate,
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
            ])->assertSessionHasNoErrors();

            $this->assertSame(
                $expected,
                PurchaseOrder::query()->latest('id')->firstOrFail()->items->first()->unit_cost_cents,
                'taux '.$rate,
            );
        }
    }

    public function test_a_blank_cost_still_prefills_from_the_supplier_price_unconverted(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['supplier_price_cents' => 1234]);

        // Le prix d'achat de la fiche est déjà HT : le taux ne doit pas le
        // diviser une seconde fois.
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1234, PurchaseOrder::query()->firstOrFail()->items->first()->unit_cost_cents);
    }

    public function test_an_unknown_vat_rate_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '19.6',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
        ])->assertSessionHasErrors('vat_rate');
    }

    public function test_the_form_offers_the_rate_selector(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/purchase-orders/create')
            ->assertOk()
            ->assertSee('name="vat_rate"', false)
            ->assertSee('Incl. VAT at 20%')
            ->assertSee('always stored excl. VAT');
    }

    public function test_the_lines_render_as_a_grid_with_one_set_of_headings(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin/purchase-orders/create')->assertOk()->getContent();

        // Les libellés une seule fois en tête, pas répétés sur chaque ligne.
        $this->assertSame(1, substr_count($html, 'po-lines-head"'));
        $this->assertStringContainsString('po-line-total', $html);
        $this->assertStringContainsString('data-total-grand', $html);
        $this->assertStringContainsString('po-item-row-template', $html);
    }

    public function test_an_edit_form_keeps_the_same_field_names(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        // Le relookage ne doit pas avoir changé le contrat du formulaire.
        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.edit', $po))
            ->assertOk()
            ->assertSee('items[0][product_id]', false)
            ->assertSee('items[0][quantity]', false)
            ->assertSee('items[0][cost]', false)
            ->assertSee('name="vat_rate"', false);
    }
}
