<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementReason;
use App\Models\Address;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderStockAllocator;
use App\Services\PurchaseOrderReceiver;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le journal de stock : ce qui a bougé, de combien, pourquoi.
 *
 * Rien n'écrit ici à la main. Un observateur pose la ligne dès qu'une
 * quantité change, ce qui rend le journal impossible à contourner tant que
 * l'écriture passe par Eloquent — et c'est le point que ces tests défendent :
 * chaque chemin qui touche au stock doit s'y retrouver, y compris ceux qui
 * ne se sont jamais présentés.
 */
class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** Le nombre de lignes de mouvement réellement affichées. */
    private function rowCount(string $html): int
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return (new \DOMXPath($document))->query('//table//tbody/tr')->length;
    }

    private function variantProduct(int $quantity = 10): Product
    {
        $product = Product::factory()->create(['quantity' => 0]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['name' => 'Taille', 'value' => 'M']],
            'sku' => 'V-M',
            'quantity' => $quantity,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product->refresh();
        $product->reconcileQuantity();

        return $product->fresh();
    }

    public function test_a_customer_order_logs_one_row_per_line(): void
    {
        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $before = $product->quantity;
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $carrier->id,
            'payment_method' => 'paypal',
        ]);

        $order = Order::query()->firstOrFail();
        $movements = StockMovement::query()->where('product_id', $product->id)->get();

        $this->assertCount(1, $movements);
        $this->assertSame(StockMovementReason::OrderPlaced, $movements->first()->reason);
        $this->assertSame(-2, $movements->first()->delta);
        $this->assertSame($before, $movements->first()->quantity_before);
        $this->assertSame($before - 2, $movements->first()->quantity_after);
        $this->assertSame($order->id, $movements->first()->subject_id);
        $this->assertSame(Order::class, $movements->first()->subject_type);
    }

    public function test_selling_a_variant_logs_exactly_one_row(): void
    {
        $product = $this->variantProduct(10);
        $variant = $product->variants()->firstOrFail();

        app(OrderStockAllocator::class)
            ->allocate($product, $variant, 3, allowBackorder: false);

        // Le total du produit est recopié de ses déclinaisons juste après le
        // vrai mouvement. Sans la règle, cette recopie ferait une deuxième
        // ligne et l'historique compterait deux fois la même vente.
        $movements = StockMovement::query()->where('product_id', $product->id)->get();

        $this->assertCount(1, $movements);
        $this->assertSame($variant->id, $movements->first()->product_variant_id);
        $this->assertSame(7, $movements->first()->quantity_after);
        $this->assertSame(7, $product->fresh()->quantity);
    }

    public function test_selling_a_product_without_variants_logs_against_the_product(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        app(OrderStockAllocator::class)
            ->allocate($product, null, 4, allowBackorder: false);

        $movement = StockMovement::query()->firstOrFail();

        $this->assertNull($movement->product_variant_id);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame(-4, $movement->delta);
    }

    public function test_the_backorder_leg_names_itself(): void
    {
        $supplier = Supplier::query()->create(['name' => 'DM Diffusion', 'lead_time_days' => 4]);
        $product = Product::factory()->create([
            'quantity' => 2,
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
        ]);

        app(OrderStockAllocator::class)
            ->allocate($product, null, 6, allowBackorder: true);

        $movement = StockMovement::query()->firstOrFail();

        // Le rayon est vidé, le reste est dû : deux unités prises, pas six.
        $this->assertSame(StockMovementReason::BackorderPartial, $movement->reason);
        $this->assertSame(-2, $movement->delta);
        $this->assertSame(0, $movement->quantity_after);
    }

    public function test_receiving_a_purchase_order_links_the_row_to_it(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['quantity' => 1]);
        $po = PurchaseOrder::factory()->sent()->create();
        $line = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => 5,
            'unit_cost_cents' => 100,
        ]);

        $this->actingAs($admin);
        app(PurchaseOrderReceiver::class)->receive($po, [$line->id => 5]);

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(StockMovementReason::PurchaseOrderReceived, $movement->reason);
        $this->assertSame(5, $movement->delta);
        $this->assertSame(6, $movement->quantity_after);
        $this->assertSame(PurchaseOrder::class, $movement->subject_type);
        $this->assertSame($po->id, $movement->subject_id);
        $this->assertSame($admin->id, $movement->user_id);
    }

    public function test_the_manual_restock_field_records_its_reason_and_its_author(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['quantity' => 4]);

        $this->actingAs($admin)->patch(route('admin.products.quantity', $product), [
            'quantity' => 9,
            'note' => 'Counted the shelf',
        ])->assertSessionHasNoErrors();

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(StockMovementReason::ManualAdjustment, $movement->reason);
        $this->assertSame(5, $movement->delta);
        $this->assertSame('Counted the shelf', $movement->note);
        $this->assertSame($admin->id, $movement->user_id);
    }

    public function test_the_product_form_logs_a_product_edit(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['quantity' => 4, 'price_cents' => 1000]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->localizedName(),
            'description' => 'Une description.',
            'category_id' => $product->category_id,
            'price' => 10,
            'quantity' => 12,
        ])->assertSessionHasNoErrors();

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(StockMovementReason::ProductEdited, $movement->reason);
        $this->assertSame(12, $movement->quantity_after);
    }

    public function test_the_json_api_logs_its_own_reason(): void
    {
        config(['services.admin_api.token' => 'test-admin-api-token']);
        $product = Product::factory()->create(['quantity' => 5]);

        $this->patchJson('/api/admin/products/'.$product->id, ['quantity' => 12], [
            'Authorization' => 'Bearer test-admin-api-token',
        ])->assertOk();

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(StockMovementReason::ApiUpdate, $movement->reason);
        $this->assertSame(7, $movement->delta);
    }

    public function test_a_change_that_declares_nothing_is_still_recorded(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);

        // Le filet, pas le chemin normal : un code nouveau qui touche au stock
        // sans se présenter doit quand même laisser une trace lisible.
        $product->update(['quantity' => 2]);

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(StockMovementReason::Unattributed, $movement->reason);
        $this->assertSame(-3, $movement->delta);
    }

    public function test_the_balance_always_matches_what_the_shelf_holds(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);

        $product->update(['quantity' => 9]);
        $product->decrement('quantity', 2);
        $product->increment('quantity', 6);

        foreach (StockMovement::query()->orderBy('id')->get() as $movement) {
            $this->assertSame($movement->quantity_before + $movement->delta, $movement->quantity_after);
        }

        $this->assertSame(
            $product->fresh()->quantity,
            StockMovement::query()->orderByDesc('id')->firstOrFail()->quantity_after,
        );
    }

    public function test_a_deleted_variant_leaves_its_history_readable(): void
    {
        $product = $this->variantProduct(10);
        $variant = $product->variants()->firstOrFail();

        $variant->decrement('quantity', 4);
        $variant->delete();

        $movement = StockMovement::query()->firstOrFail();

        // La déclinaison est partie ; son nom, figé, reste lisible.
        $this->assertNull($movement->fresh()->product_variant_id);
        $this->assertSame('M', $movement->variant_label);
        $this->assertSame('M', $movement->variantLabel());
    }

    public function test_the_page_lists_the_movements_and_filters_them(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($admin)->patch(route('admin.products.quantity', $product), [
            'quantity' => 9,
            'note' => 'Counted the shelf',
        ]);
        $product->fresh()->update(['quantity' => 3]);

        $url = route('admin.products.stock-history', $product);

        $unfiltered = $this->actingAs($admin)->get($url)->assertOk();
        $unfiltered->assertSee('Counted the shelf');

        $this->assertSame(2, $this->rowCount($unfiltered->getContent()));

        // Le libellé d'une raison figure aussi dans le menu du filtre :
        // seul le compte des lignes dit ce qui est réellement listé.
        $filtered = $this->actingAs($admin)->get($url.'?reason=manual_adjustment')->assertOk();
        $filtered->assertSee('Counted the shelf');

        $this->assertSame(1, $this->rowCount($filtered->getContent()));
    }

    public function test_the_page_filters_by_variant(): void
    {
        $admin = $this->admin();
        $product = $this->variantProduct(10);
        $first = $product->variants()->firstOrFail();
        $second = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['name' => 'Taille', 'value' => 'L']],
            'sku' => 'V-L',
            'quantity' => 8,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $first->decrement('quantity', 1);
        $second->decrement('quantity', 2);

        $url = route('admin.products.stock-history', $product);

        $this->assertSame(2, $this->rowCount($this->actingAs($admin)->get($url)->getContent()));

        $filtered = $this->actingAs($admin)->get($url.'?variant='.$second->id)->assertOk();

        $this->assertSame(1, $this->rowCount($filtered->getContent()));

        // La déclinaison L partait de 8, la M de 10 : le solde d'origine dit
        // laquelle des deux est restée, là où les libellés se répètent dans
        // le menu du filtre.
        $filtered->assertSee('from 8');
        $filtered->assertDontSee('from 10');
    }

    public function test_the_page_paginates(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['quantity' => 0]);
        StockMovement::factory()->count(60)->create(['product_id' => $product->id]);

        $this->actingAs($admin)
            ->get(route('admin.products.stock-history', $product))
            ->assertOk()
            ->assertSee('Next');
    }

    public function test_the_drift_banner_fires_when_something_wrote_outside_the_log(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['quantity' => 5]);
        $product->update(['quantity' => 9]);

        $this->actingAs($admin)
            ->get(route('admin.products.stock-history', $product))
            ->assertOk()
            ->assertDontSee('Something changed stock outside this log.');

        // Une écriture au query builder ne déclenche aucun événement de
        // modèle : c'est exactement le trou que le solde enregistré révèle.
        Product::query()->whereKey($product->id)->update(['quantity' => 42]);

        $this->actingAs($admin)
            ->get(route('admin.products.stock-history', $product))
            ->assertOk()
            ->assertSee('Something changed stock outside this log.');
    }

    public function test_an_empty_log_says_so_without_sounding_broken(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($this->admin())
            ->get(route('admin.products.stock-history', $product))
            ->assertOk()
            ->assertSee('No stock movements recorded yet.')
            ->assertDontSee('Something changed stock outside this log.');
    }

    public function test_staff_can_read_the_history(): void
    {
        $staff = User::factory()->admin()->create(['role' => 'staff']);
        $product = Product::factory()->create(['quantity' => 5]);

        // Lecture seule, sans prix d'achat ni paiement : celui qui
        // réceptionne la marchandise est celui qui a besoin de la relire.
        $this->actingAs($staff)
            ->get(route('admin.products.stock-history', $product))
            ->assertOk();
    }

    public function test_refunding_an_order_moves_no_stock(): void
    {
        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();
        $address = Address::factory()->for($user)->create();
        $carrier = Carrier::query()->where('slug', 'colissimo-home')->firstOrFail();

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($user)->post('/checkout', [
            'address_id' => $address->id,
            'same_billing_address' => true,
            'carrier_id' => $carrier->id,
            'payment_method' => 'paypal',
        ]);

        $order = Order::query()->firstOrFail();
        $quantityAfterSale = $product->fresh()->quantity;
        $movementsAfterSale = StockMovement::query()->count();

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.refund', $order))
            ->assertRedirect();

        // Un remboursement ne remet rien en rayon aujourd'hui, et ce choix
        // tient : la marchandise n'est pas revenue pour autant.
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame($quantityAfterSale, $product->fresh()->quantity);
        $this->assertSame($movementsAfterSale, StockMovement::query()->count());
    }
}
