<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Le rattrapage du journal depuis les commandes déjà passées.
 *
 * Deux promesses tiennent tout : aucune quantité ne bouge, et le solde
 * reconstruit retombe exactement sur le stock d'aujourd'hui. La seconde est
 * ce qui garde la bannière de dérive muette après le rattrapage — sinon la
 * page accuserait à tort chacun des quatre-vingt-huit produits concernés.
 */
class StockMovementBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'delivered', ?Carbon $placedAt = null, bool $test = false): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            'test_marked_at' => $test ? now() : null,
        ]);

        if ($placedAt !== null) {
            $order->statusHistories()->update(['created_at' => $placedAt]);
            $order->forceFill(['created_at' => $placedAt])->save();
        }

        return $order->fresh('statusHistories');
    }

    private function line(Order $order, ?Product $product, int $quantity): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'product_slug' => $product?->slug ?? 'gone',
            'name' => $product?->name ?? ['fr' => 'Article supprimé'],
            'image' => $product?->image ?? '',
            'quantity' => $quantity,
            'unit_price_cents' => 500,
            'line_cents' => 500 * $quantity,
        ]);
    }

    private function receivedPurchaseOrder(Product $product, int $quantity, Carbon $receivedAt): PurchaseOrder
    {
        $po = PurchaseOrder::factory()->create(['status' => 'received', 'received_at' => $receivedAt]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => $quantity,
            'quantity_received' => $quantity,
            'unit_cost_cents' => 100,
        ]);

        $po->statusHistories()->create(['status' => 'received']);
        $po->statusHistories()->where('status', 'received')->update(['created_at' => $receivedAt]);

        return $po->fresh(['items', 'statusHistories']);
    }

    private function backfill(array $options = []): string
    {
        $this->artisan('stock:backfill-history', $options)->assertSuccessful();

        return '';
    }

    public function test_it_writes_one_movement_per_line_and_touches_no_quantity(): void
    {
        $product = Product::factory()->create(['quantity' => 6]);
        $order = $this->order(placedAt: Carbon::parse('2026-05-04 10:00:00'));
        $this->line($order, $product, 2);

        $this->backfill();

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(6, $product->fresh()->quantity);
        $this->assertSame(StockMovementReason::OrderPlaced, $movement->reason);
        $this->assertSame(-2, $movement->delta);
        $this->assertSame(Order::class, $movement->subject_type);
        $this->assertSame($order->id, $movement->subject_id);
        $this->assertSame('2026-05-04', $movement->created_at->format('Y-m-d'));
    }

    public function test_the_reconstructed_balance_lands_on_the_stock_of_today(): void
    {
        $product = Product::factory()->create(['quantity' => 4]);

        $old = $this->order(placedAt: Carbon::parse('2026-05-01 10:00:00'));
        $this->line($old, $product, 3);
        $recent = $this->order(placedAt: Carbon::parse('2026-06-01 10:00:00'));
        $this->line($recent, $product, 2);

        $this->backfill();

        $movements = StockMovement::query()->orderBy('created_at')->get();

        // On remonte le temps depuis le stock actuel : la vente la plus
        // récente atterrit sur 4, celle d'avant sur ce qu'elle lui a laissé.
        $this->assertSame([9, 6], $movements->pluck('quantity_before')->all());
        $this->assertSame([6, 4], $movements->pluck('quantity_after')->all());

        foreach ($movements as $movement) {
            $this->assertSame($movement->quantity_before + $movement->delta, $movement->quantity_after);
        }
    }

    public function test_the_drift_banner_stays_quiet_after_a_backfill(): void
    {
        $product = Product::factory()->create(['quantity' => 4]);
        $order = $this->order(placedAt: Carbon::parse('2026-05-01 10:00:00'));
        $this->line($order, $product, 3);

        $this->backfill();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.stock-history', $product))
            ->assertOk()
            ->assertDontSee('Something changed stock outside this log.');
    }

    public function test_test_orders_and_drafts_are_left_out(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);

        $this->line($this->order(test: true), $product, 1);
        $this->line($this->order(status: 'draft'), $product, 1);

        $this->backfill();

        // Un brouillon n'a jamais pris de stock, et une commande de test ne
        // pèse sur aucun chiffre de la boutique.
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_a_line_whose_product_is_gone_is_skipped(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $order = $this->order();
        $this->line($order, $product, 1);
        $this->line($order, null, 4);

        $this->backfill();

        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_running_it_twice_leaves_the_same_ledger(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $this->line($this->order(), $product, 2);

        $this->backfill();
        $first = StockMovement::query()->get(['delta', 'quantity_before', 'quantity_after'])->toArray();

        $this->backfill();

        // Le rattrapage réécrit ses propres lignes plutôt que d'en ajouter :
        // c'est ce qui lui permet de refaire une chronologie entière quand
        // une source s'ajoute à l'autre.
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame($first, StockMovement::query()->get(['delta', 'quantity_before', 'quantity_after'])->toArray());
    }

    public function test_it_never_touches_a_movement_that_was_actually_observed(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $this->line($this->order(placedAt: Carbon::parse('2026-05-01 10:00:00')), $product, 2);

        // Un mouvement réel : observé, daté, avec son solde vrai.
        $product->update(['quantity' => 9]);
        $observed = StockMovement::query()->firstOrFail();

        $this->backfill();

        $this->assertNotNull($observed->fresh());
        $this->assertFalse($observed->fresh()->backfilled);

        // La remontée repart d'où le mouvement observé a commencé, pas du
        // stock d'aujourd'hui : sinon les deux moitiés du journal ne se
        // rejoindraient pas.
        $backfilled = StockMovement::query()->where('backfilled', true)->firstOrFail();

        $this->assertSame($observed->quantity_before, $backfilled->quantity_after);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $this->line($this->order(), $product, 2);

        $this->artisan('stock:backfill-history', ['--dry-run' => true])
            ->expectsOutputToContain('1 movements')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(5, $product->fresh()->quantity);
    }

    public function test_a_backfilled_row_says_that_its_balance_was_reconstructed(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $this->line($this->order(), $product, 2);

        $this->backfill();

        // Le solde d'une ligne rattrapée est cohérent, pas véridique : une
        // reconstruction ne sait que remonter, donc elle ignore les
        // réassorts qui se sont glissés entre deux ventes.
        $this->assertStringContainsString(
            'reconstructed',
            (string) StockMovement::query()->firstOrFail()->note,
        );
    }

    public function test_a_receipt_is_written_as_stock_coming_in(): void
    {
        $product = Product::factory()->create(['quantity' => 12]);
        $po = $this->receivedPurchaseOrder($product, 5, Carbon::parse('2026-07-29 09:00:00'));

        $this->backfill();

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(12, $product->fresh()->quantity);
        $this->assertSame(StockMovementReason::PurchaseOrderReceived, $movement->reason);
        $this->assertSame(5, $movement->delta);
        $this->assertSame(7, $movement->quantity_before);
        $this->assertSame(12, $movement->quantity_after);
        $this->assertSame(PurchaseOrder::class, $movement->subject_type);
        $this->assertSame($po->id, $movement->subject_id);
        $this->assertSame('2026-07-29', $movement->created_at->format('Y-m-d'));
    }

    public function test_sales_and_receipts_share_one_chronology(): void
    {
        $product = Product::factory()->create(['quantity' => 4]);

        $this->line($this->order(placedAt: Carbon::parse('2026-05-01 10:00:00')), $product, 3);
        $this->receivedPurchaseOrder($product, 6, Carbon::parse('2026-06-01 10:00:00'));
        $this->line($this->order(placedAt: Carbon::parse('2026-07-01 10:00:00')), $product, 2);

        $this->backfill();

        $movements = StockMovement::query()->orderBy('created_at')->get();

        // Reconstruites séparément, les ventes seules donneraient 9 → 6 → 4
        // et la réception se poserait à côté. Ensemble, la chaîne se tient :
        // chaque solde de départ est celui d'arrivée du mouvement précédent.
        $this->assertSame([-3, 6, -2], $movements->pluck('delta')->all());
        $this->assertSame([3, 0, 6], $movements->pluck('quantity_before')->all());
        $this->assertSame([0, 6, 4], $movements->pluck('quantity_after')->all());

        foreach ($movements as $index => $movement) {
            if ($index > 0) {
                $this->assertSame($movements[$index - 1]->quantity_after, $movement->quantity_before);
            }
        }
    }

    public function test_a_purchase_order_that_was_never_received_writes_nothing(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $po = PurchaseOrder::factory()->sent()->create();
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 8,
            'quantity_received' => 0,
            'unit_cost_cents' => 100,
        ]);

        $this->backfill();

        // Commander n'est pas recevoir : rien n'est encore arrivé en rayon.
        $this->assertSame(0, StockMovement::query()->count());
    }
}
