<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\PurchaseOrderReceiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * La réception d'un bon de commande — le seul endroit où il touche au stock.
 *
 * Le stock monte à mesure que la marchandise arrive, livraison par livraison,
 * et chaque réception laisse une ligne d'historique disant qui a reçu quoi.
 * Annuler ne reprend jamais ce qui est déjà arrivé.
 */
class PurchaseOrderReceivingTest extends TestCase
{
    use RefreshDatabase;

    private function sentOrder(array $lines): PurchaseOrder
    {
        $po = PurchaseOrder::factory()->sent()->create();

        foreach ($lines as $line) {
            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $line['product']?->id,
                'product_variant_id' => $line['variant']->id ?? null,
                'name' => $line['product']?->localizedName() ?? 'Article supprimé',
                'sku' => $line['product']?->sku,
                'quantity_ordered' => $line['quantity'],
                'unit_cost_cents' => $line['cost'] ?? 100,
            ]);
        }

        return $po->fresh('items');
    }

    private function receive(PurchaseOrder $po, array $lines): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.purchase-orders.receive', $po), ['lines' => $lines]);
    }

    public function test_receiving_in_full_closes_the_order_and_raises_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 2]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 5]]);

        $this->receive($po, [$po->items->first()->id => 5]);

        $po->refresh();
        $this->assertSame('received', $po->status);
        $this->assertNotNull($po->received_at);
        $this->assertSame(7, $product->fresh()->quantity);
    }

    public function test_a_partial_receipt_leaves_the_order_open(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 10]]);

        $this->receive($po, [$po->items->first()->id => 4]);

        $po->refresh();
        $this->assertSame('partially_received', $po->status);
        $this->assertNull($po->received_at);
        $this->assertSame(4, $product->fresh()->quantity);
        $this->assertSame(6, $po->items->first()->quantityRemaining());
    }

    public function test_a_second_receipt_completes_it(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 10]]);
        $line = $po->items->first();

        $this->receive($po, [$line->id => 4]);
        $this->receive($po, [$line->id => 6]);

        $this->assertSame('received', $po->fresh()->status);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_each_receipt_writes_a_history_row_naming_what_arrived(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 10]]);
        $line = $po->items->first();

        $this->receive($po, [$line->id => 4]);
        $this->receive($po, [$line->id => 2]);

        $notes = $po->fresh()->statusHistories->pluck('note')->filter();

        // Deux réceptions, deux lignes — même quand le statut ne change pas.
        $this->assertCount(2, $notes);
        $this->assertStringContainsString('4 × '.$product->localizedName(), $notes->last());
        $this->assertStringContainsString('2 × '.$product->localizedName(), $notes->first());
    }

    public function test_over_receiving_is_rejected_and_moves_no_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 3]]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.purchase-orders.receive', $po), ['lines' => [$po->items->first()->id => 4]])
            ->assertSessionHasErrors('lines.'.$po->items->first()->id);

        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertSame('sent', $po->fresh()->status);
    }

    public function test_receiving_a_variant_reconciles_the_parent_total(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'quantity' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $po = $this->sentOrder([['product' => $product, 'variant' => $variant, 'quantity' => 6]]);

        $this->receive($po, [$po->items->first()->id => 6]);

        $this->assertSame(8, $variant->fresh()->quantity);
        // Le total du produit est le miroir de la somme de ses déclinaisons.
        $this->assertSame(8, $product->fresh()->quantity);
    }

    public function test_a_deleted_product_books_the_receipt_without_stock_or_error(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 3]]);
        $product->delete();

        $this->receive($po, [$po->items->first()->id => 3]);

        $po->refresh();
        $this->assertSame('received', $po->status);
        $this->assertSame(3, $po->items->first()->quantity_received);
    }

    public function test_a_product_that_gained_variants_is_not_credited_directly(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 5]]);

        // Depuis le bon, le produit a gagné des tailles : son total ne lui
        // appartient plus, l'écrire serait effacé au prochain recalcul.
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->receive($po, [$po->items->first()->id => 5]);

        $this->assertSame(5, $po->fresh()->items->first()->quantity_received);
        // Ni le produit ni la taille n'ont bougé : la garde a tenu.
        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertSame(1, ProductVariant::query()->first()->quantity);
    }

    public function test_receiving_zero_everywhere_is_refused(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 3]]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.purchase-orders.receive', $po), ['lines' => [$po->items->first()->id => 0]])
            ->assertSessionHasErrors('lines');
    }

    public function test_a_draft_cannot_be_received(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = PurchaseOrder::factory()->create();
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 3,
            'unit_cost_cents' => 100,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.purchase-orders.receive', $po), ['lines' => [$po->items()->first()->id => 3]])
            ->assertForbidden();

        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_cancelling_a_partially_received_order_keeps_the_stock(): void
    {
        $owner = User::factory()->admin()->create();
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 10]]);

        $this->receive($po, [$po->items->first()->id => 4]);
        $this->actingAs($owner)->patch(route('admin.purchase-orders.cancel', $po))->assertRedirect();

        // La marchandise déjà arrivée est arrivée : annuler ne clôt que le reste.
        $this->assertSame('cancelled', $po->fresh()->status);
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_a_line_from_another_order_cannot_be_received_here(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 3]]);
        $other = $this->sentOrder([['product' => $product, 'quantity' => 3]]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.purchase-orders.receive', $po), ['lines' => [$other->items->first()->id => 3]])
            ->assertNotFound();

        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_the_receiver_credits_each_unit_exactly_once(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $po = $this->sentOrder([['product' => $product, 'quantity' => 5]]);
        $line = $po->items->first();
        $receiver = app(PurchaseOrderReceiver::class);

        $receiver->receive($po, [$line->id => 5]);

        // Rejouer la même livraison — un double clic, un onglet resté ouvert —
        // doit échouer au lieu de créditer les unités une seconde fois.
        try {
            $receiver->receive($po->fresh(), [$line->id => 5]);
            $this->fail('la seconde réception aurait dû être refusée');
        } catch (ValidationException|HttpException) {
            // attendu : soit la ligne n'a plus de reste, soit le bon est clos
        }

        $this->assertSame(5, $product->fresh()->quantity);
    }
}
