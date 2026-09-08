<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSetting;
use App\Models\User;
use App\Services\DashboardPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le tableau de bord, cadré par une période.
 *
 * Une seule tranche de temps porte la page : si un panneau regardait
 * ailleurs que les autres, deux chiffres côte à côte se contrediraient.
 * Les écarts se comparent à la tranche précédente de même longueur, et une
 * tranche précédente vide n'a pas de pourcentage à afficher.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(array $overrides = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            ...$overrides,
        ]);
    }

    /** Receive $quantity units of $product at $unitCostCents excl. VAT. */
    private function receive(Product $product, int $quantity, int $unitCostCents, int $vatBasisPoints = 2000): \App\Models\PurchaseOrder
    {
        $supplier = \App\Models\Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);

        $purchaseOrder = \App\Models\PurchaseOrder::query()->create([
            'number' => 'BC-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'supplier_id' => $supplier->id,
            'supplier_name' => 'Fournisseur',
            'status' => 'received',
            'vat_rate_basis_points' => $vatBasisPoints,
        ]);

        \App\Models\PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'sku' => $product->sku,
            'quantity_ordered' => $quantity,
            'quantity_received' => $quantity,
            'unit_cost_cents' => $unitCostCents,
        ]);

        return $purchaseOrder;
    }

    private function orderAt(string $date, int $totalCents = 1500): Order
    {
        $order = $this->order(['total_cents' => $totalCents]);
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }

    private function dashboard(?string $period = null)
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.dashboard', $period ? ['period' => $period] : []))
            ->assertOk();
    }

    public function test_the_period_scopes_revenue_and_orders(): void
    {
        $this->orderAt(now()->subDays(2)->toDateTimeString(), 1000);
        $this->orderAt(now()->subDays(45)->toDateTimeString(), 9999);

        $sevenDays = $this->dashboard('7d')->viewData('headline');
        $ninetyDays = $this->dashboard('90d')->viewData('headline');

        $this->assertSame(1000, $sevenDays['revenue_cents']);
        $this->assertSame(1, $sevenDays['orders']);

        $this->assertSame(10999, $ninetyDays['revenue_cents']);
        $this->assertSame(2, $ninetyDays['orders']);
    }

    public function test_the_delta_compares_against_the_previous_window_of_the_same_length(): void
    {
        // 7 jours courants : 100,00 €. Les 7 jours d'avant : 50,00 €.
        $this->orderAt(now()->subDays(1)->toDateTimeString(), 10000);
        $this->orderAt(now()->subDays(9)->toDateTimeString(), 5000);

        $headline = $this->dashboard('7d')->viewData('headline');

        $this->assertSame(10000, $headline['revenue_cents']);
        $this->assertSame(100.0, $headline['revenue_delta']['percent']);
        $this->assertSame('up', $headline['revenue_delta']['direction']);
    }

    public function test_an_empty_previous_window_renders_a_dash_rather_than_dividing_by_zero(): void
    {
        $this->orderAt(now()->subDay()->toDateTimeString(), 4200);

        $response = $this->dashboard('7d');
        $headline = $response->viewData('headline');

        // Croître depuis zéro n'a pas de pourcentage : ni « +∞ % », ni un plantage.
        $this->assertNull($headline['revenue_delta']['percent']);
        $response->assertSee('—');
    }

    public function test_top_products_reflect_the_period_rather_than_all_time(): void
    {
        $recent = Product::factory()->create(['name' => ['fr' => 'Sold recently']]);
        $old = Product::factory()->create(['name' => ['fr' => 'Sold long ago']]);

        $recentOrder = $this->orderAt(now()->subDay()->toDateTimeString());
        OrderItem::query()->create([
            'order_id' => $recentOrder->id, 'product_id' => $recent->id, 'product_slug' => $recent->slug,
            'name' => ['fr' => 'Sold recently'], 'image' => '', 'quantity' => 1,
            'unit_price_cents' => 1000, 'line_cents' => 1000,
        ]);

        $oldOrder = $this->orderAt(now()->subDays(60)->toDateTimeString());
        OrderItem::query()->create([
            'order_id' => $oldOrder->id, 'product_id' => $old->id, 'product_slug' => $old->slug,
            'name' => ['fr' => 'Sold long ago'], 'image' => '', 'quantity' => 99,
            'unit_price_cents' => 1000, 'line_cents' => 99000,
        ]);

        $sevenDays = $this->dashboard('7d')->viewData('topProducts');

        // Le vieux produit domine en tout-temps mais n'a rien vendu ici.
        $this->assertSame(['Sold recently'], $sevenDays->pluck('name')->all());

        $ninetyDays = $this->dashboard('90d')->viewData('topProducts');
        $this->assertSame('Sold long ago', $ninetyDays->first()['name']);
    }

    public function test_the_attention_strip_is_absent_when_nothing_needs_attention(): void
    {
        // Une bande d'alerte toujours présente apprend à l'ignorer.
        $this->dashboard()
            ->assertDontSee('Needs attention')
            ->assertDontSee('dash-attention-list', false);
    }

    public function test_the_attention_strip_appears_when_something_needs_attention(): void
    {
        $this->order(['status' => 'placed']);

        $response = $this->dashboard();

        $response->assertSee('Needs attention');
        $this->assertSame(
            1,
            (int) $response->viewData('attention')->firstWhere('key', 'to-prepare')['count'],
        );
    }

    public function test_every_period_option_renders(): void
    {
        $this->orderAt(now()->subDay()->toDateTimeString());

        foreach (array_keys(DashboardPeriod::OPTIONS) as $period) {
            $this->dashboard($period)->assertSee('Revenue');
        }
    }

    public function test_an_unknown_period_falls_back_to_the_default(): void
    {
        $this->assertSame('30d', $this->dashboard('nonsense')->viewData('period')->key);
    }

    public function test_the_table_views_carry_every_value_without_javascript(): void
    {
        $this->orderAt(now()->subDay()->toDateTimeString(), 2500);

        $html = $this->dashboard('7d')->getContent();

        // Le canvas reste vide sans JS : le tableau rendu côté serveur est le
        // seul moyen de lire les valeurs, il doit donc toujours être là.
        $this->assertStringContainsString('dash-table-view', $html);
        $this->assertStringContainsString('25,00', $html);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_orders(): void
    {
        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        // Un produit distinct par commande : c'est le nombre de références
        // vendues, pas de commandes, qui faisait exploser l'ancien calcul —
        // il chargeait un produit par référence avant même d'en garder cinq.
        $seed = function (int $howMany): void {
            foreach (range(1, $howMany) as $i) {
                $product = Product::factory()->create();
                $order = $this->orderAt(now()->subDays($i % 20)->toDateTimeString());
                OrderItem::query()->create([
                    'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
                    'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 1,
                    'unit_price_cents' => 1000, 'line_cents' => 1000,
                ]);
            }
        };

        // Warm the low-stock threshold: once() memoizes it for the process,
        // so the first render would pay its firstOrCreate and the second
        // would not — a difference that is not the dashboard's own work.
        ProductSetting::lowStockThreshold();

        $seed(5);
        $small = $measure();

        $seed(45);
        $large = $measure();

        // La version précédente chargeait chaque ligne de commande puis un
        // produit par référence distincte : le compte grimpait avec le
        // catalogue. Il doit maintenant être plat.
        $this->assertSame($small, $large, "Query count grew from {$small} to {$large} — an N+1 came back.");
    }

    public function test_the_catalogue_strip_counts_references_and_shelf_stock(): void
    {
        // One plain active product, one with two declinations, one inactive
        // plain product, two active but empty ones: 5 references for sale,
        // 12 units on the shelves - the inactive product's 10 count nowhere.
        Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        $sized = Product::factory()->create(['is_active' => true, 'quantity' => 0]);
        foreach ([['M', 3], ['L', 4]] as [$size, $quantity]) {
            \App\Models\ProductVariant::query()->create([
                'product_id' => $sized->id,
                'attribute_values' => [['label' => 'Taille', 'value' => $size]],
                'sku' => 'VAR-'.$size,
                'price_cents' => 1999,
                'quantity' => $quantity,
                'is_active' => true,
            ]);
        }
        $sleeping = Product::factory()->create(['is_active' => false, 'quantity' => 10]);
        // Active but empty, with or without the supplier's shelf behind it:
        // still for sale, still a reference - the stock says the rest.
        Product::factory()->create(['is_active' => true, 'quantity' => 0, 'available_at_supplier' => false]);
        Product::factory()->create(['is_active' => true, 'quantity' => 0, 'available_at_supplier' => true]);

        // An open purchase order: 6 still awaited for the active plain
        // product (7 ordered, 1 in), 20 for the sleeping one, which counts
        // as one reference to receive and nothing in the stock to receive.
        $supplier = \App\Models\Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $purchaseOrder = \App\Models\PurchaseOrder::query()->create([
            'number' => 'BC-TEST-0001', 'supplier_id' => $supplier->id, 'supplier_name' => 'Fournisseur', 'status' => 'sent',
        ]);
        $awake = Product::query()->where('is_active', true)->whereDoesntHave('variants')->firstOrFail();
        foreach ([[$awake, 7, 1], [$sleeping, 20, 0]] as [$product, $ordered, $received]) {
            \App\Models\PurchaseOrderItem::query()->create([
                'purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id,
                'name' => $product->localizedName(), 'sku' => $product->sku,
                'quantity_ordered' => $ordered, 'quantity_received' => $received, 'unit_cost_cents' => 100,
            ]);
        }

        // The four figures live in the Warehouse panel since they joined the
        // stock value there: same counts, same rule, one strip of tiles
        // fewer.
        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('References for sale')
            ->assertSee('Units in stock')
            ->assertSee('Still to receive')
            ->getContent();

        $this->assertMatchesRegularExpression('#Still to receive</span>\s*<span class="dash-fact-value">6</span>#', $html);
        $this->assertStringContainsString('1 reference not yet for sale', $html);

        $this->assertMatchesRegularExpression('#References for sale</span>\s*<span class="dash-fact-value">5</span>#', $html);
        $this->assertStringContainsString('4 products · 2 variants', $html);
        $this->assertMatchesRegularExpression('#Units in stock</span>\s*<span class="dash-fact-value">12</span>#', $html);
    }

    public function test_the_ledger_subtracts_costs_and_goods_from_revenue(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 10, 100); // 1,20 € TTC l'unité

        // 15,00 € taken, 3,00 € of costs, 2 units at 1,20 €:
        // 15,00 − 3,00 − 2,40 = 9,60 € of profit.
        $order = $this->order([
            'total_cents' => 1500,
            'shipping_paid_cents' => 100,
            'marketplace_commission_cents' => 150,
            'payment_fee_cents' => 50,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
            'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 2,
            'unit_price_cents' => 750, 'line_cents' => 1500,
        ]);

        $money = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->money();

        $this->assertSame(1500, $money['revenue_cents']);
        $this->assertSame(300, $money['order_costs_cents']);
        $this->assertSame(240, $money['product_cost_cents']);
        $this->assertSame(960, $money['profit_cents']);
        $this->assertSame(64.0, $money['margin_percent']);
        $this->assertSame(1, $money['priced_orders']);
        $this->assertSame(1, $money['total_orders']);
    }

    public function test_an_order_that_cannot_be_priced_stays_out_of_the_profit(): void
    {
        // No purchase order behind this product: the profit on this sale is
        // unknown, not nothing. It counts towards revenue, and the counter
        // says it is missing from the rest.
        $order = $this->order(['total_cents' => 2000]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => Product::factory()->create()->id,
            'product_slug' => 'x', 'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 1,
            'unit_price_cents' => 2000, 'line_cents' => 2000,
        ]);

        $money = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->money();

        $this->assertSame(2000, $money['revenue_cents']);
        $this->assertSame(0, $money['product_cost_cents']);
        $this->assertSame(0, $money['profit_cents']);
        $this->assertSame(0, $money['priced_orders']);
        $this->assertSame(1, $money['total_orders']);
        // With no priced order there is no margin to write.
        $this->assertNull($money['margin_percent']);
    }

    public function test_the_ledger_bar_keeps_one_base_for_its_three_shares(): void
    {
        // Two sales, only one priceable: the bar is a share of that one's
        // revenue, so its three parts add up to 100 %.
        $product = Product::factory()->create();
        $this->receive($product, 10, 100);

        $priced = $this->order(['total_cents' => 1500, 'payment_fee_cents' => 60]);
        OrderItem::query()->create([
            'order_id' => $priced->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
            'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 2,
            'unit_price_cents' => 750, 'line_cents' => 1500,
        ]);

        $unpriced = $this->order(['total_cents' => 5000, 'payment_fee_cents' => 200]);
        OrderItem::query()->create([
            'order_id' => $unpriced->id, 'product_id' => Product::factory()->create()->id,
            'product_slug' => 'y', 'name' => ['fr' => 'Y'], 'image' => '', 'quantity' => 1,
            'unit_price_cents' => 5000, 'line_cents' => 5000,
        ]);

        $money = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->money();

        $this->assertSame(6500, $money['revenue_cents']);
        $this->assertSame(1500, $money['priced_revenue_cents']);
        // The costs of the priced order only, not of both.
        $this->assertSame(60, $money['priced_costs_cents']);
        $this->assertSame(260, $money['order_costs_cents']);

        $base = $money['priced_revenue_cents'];
        $shares = ($money['priced_costs_cents'] + $money['product_cost_cents'] + $money['profit_cents']) / $base;

        $this->assertEqualsWithDelta(1.0, $shares, 0.0001);
    }

    public function test_the_warehouse_values_the_shelves_at_average_purchase_cost(): void
    {
        $priced = Product::factory()->create(['is_active' => true, 'quantity' => 4]);
        $this->receive($priced, 10, 250); // 3,00 € TTC l'unité

        // A reference in stock with no purchase history has no known value:
        // counted separately, never at zero.
        Product::factory()->create(['is_active' => true, 'quantity' => 7]);

        $stock = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->stockValue();

        $this->assertSame(1200, $stock['warehouse_cents']);
        $this->assertSame(4, $stock['valued_units']);
        $this->assertSame(1, $stock['unpriced_references']);
    }

    public function test_a_product_with_declinations_is_valued_on_its_declinations(): void
    {
        // The product's own column stays filled behind its declinations. It
        // is the declinations' that counts — the "Units in stock" tile counts
        // that way, and two figures in one panel cannot count the same stock
        // twice.
        $sized = Product::factory()->create(['is_active' => true, 'quantity' => 9]);
        $this->receive($sized, 10, 250); // 3,00 € TTC l'unité

        foreach ([['M', 2], ['L', 3]] as [$size, $quantity]) {
            \App\Models\ProductVariant::query()->create([
                'product_id' => $sized->id,
                'attribute_values' => [['label' => 'Taille', 'value' => $size]],
                'sku' => 'VAR-'.$size,
                'price_cents' => 1999,
                'quantity' => $quantity,
                'is_active' => true,
            ]);
        }

        $stock = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->stockValue();

        // 5 declined units at 3,00 €, never 14.
        $this->assertSame(5, $stock['valued_units']);
        $this->assertSame(1500, $stock['warehouse_cents']);
    }

    public function test_the_warehouse_counts_what_is_still_owed_to_suppliers(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 0]);

        $supplier = \App\Models\Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $open = \App\Models\PurchaseOrder::query()->create([
            'number' => 'BC-OPEN-1', 'supplier_id' => $supplier->id, 'supplier_name' => 'Fournisseur',
            'status' => 'sent', 'vat_rate_basis_points' => 2000,
        ]);
        \App\Models\PurchaseOrderItem::query()->create([
            'purchase_order_id' => $open->id, 'product_id' => $product->id,
            'name' => $product->localizedName(), 'sku' => $product->sku,
            'quantity_ordered' => 10, 'quantity_received' => 4, 'unit_cost_cents' => 500,
        ]);

        $stock = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->stockValue();

        // Six units still owed at 5,00 € excl. VAT, VAT 20 %: 36,00 €.
        $this->assertSame(3600, $stock['committed_cents']);
        $this->assertSame(1, $stock['open_purchase_orders']);
    }

    public function test_a_buyer_who_bought_before_the_period_counts_as_returning(): void
    {
        $loyal = User::factory()->create();
        $fresh = User::factory()->create();

        $old = $this->order(['user_id' => $loyal->id]);
        $old->forceFill(['created_at' => now()->subDays(90)])->save();

        $this->order(['user_id' => $loyal->id]);
        $this->order(['user_id' => $fresh->id]);

        $customers = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->customers();

        $this->assertSame(2, $customers['buyers']);
        $this->assertSame(1, $customers['returning']);
        $this->assertSame(1, $customers['new']);
        $this->assertSame(50.0, $customers['returning_percent']);
        // Two orders for the loyal one, one for the other: one customer in
        // two bought more than once.
        $this->assertSame(1, $customers['repeat_buyers']);
    }

    public function test_the_channel_split_takes_the_commission_off_each_channel(): void
    {
        $this->order([
            'total_cents' => 10000,
            'marketplace_name' => 'NaturaBuy',
            'marketplace_commission_cents' => 1400,
        ]);
        $this->order(['total_cents' => 5000]);

        $split = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->channelSplit();

        $marketplace = $split->firstWhere('label', 'NaturaBuy');
        $direct = $split->firstWhere('label', 'Direct sale');

        $this->assertSame(1400, $marketplace['commission_cents']);
        $this->assertSame(8600, $marketplace['net_cents']);
        // A direct sale pays commission to nobody.
        $this->assertSame(0, $direct['commission_cents']);
        $this->assertSame(5000, $direct['net_cents']);
    }

    public function test_the_page_shows_the_ledger_the_warehouse_and_the_customers(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 3]);
        $this->receive($product, 10, 100);

        $order = $this->order(['total_cents' => 1500, 'payment_fee_cents' => 50]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
            'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 2,
            'unit_price_cents' => 750, 'line_cents' => 1500,
        ]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('What the shop kept')
            ->assertSee('Selling costs')
            ->assertSee('Goods')
            ->assertSee('Profit')
            ->assertSee('Warehouse')
            ->assertSee('Customers')
            ->assertSee('Orders per day')
            // The ledger bar and its three segments.
            ->assertSee('dash-ledger-segment is-profit', false);
    }

    public function test_all_time_starts_at_the_first_sale(): void
    {
        // A test order never happened: it cannot open the period, or the
        // page would start on empty months.
        $ghost = $this->orderAt('2024-01-05');
        $ghost->forceFill(['test_marked_at' => now()])->save();

        $this->orderAt('2026-03-11');
        $this->orderAt('2026-05-02');

        $period = DashboardPeriod::resolve('all');

        $this->assertSame('2026-03-11', $period->start->toDateString());
        $this->assertSame('All time', $period->label());
    }

    public function test_all_time_has_no_earlier_period_to_compare_against(): void
    {
        $this->orderAt('2026-05-02', 5000);

        $period = DashboardPeriod::resolve('all');

        // The previous window ends before it begins: it is empty, so the
        // delta has nothing to measure against.
        $this->assertTrue($period->previousEnd->lessThan($period->previousStart));

        $metrics = new \App\Services\DashboardMetrics($period);

        $this->assertSame(0, $metrics->revenueSeries()['previous']->count());
        $this->assertNull($metrics->headline()['revenue_delta']['percent']);
        $this->assertSame('since the first sale', $period->comparisonLabel());
    }

    public function test_a_long_period_counts_by_month_rather_than_by_day(): void
    {
        $this->orderAt(now()->subMonths(5)->startOfMonth()->toDateTimeString(), 1000);
        $this->orderAt(now()->startOfMonth()->toDateTimeString(), 2000);

        $period = DashboardPeriod::resolve('all');

        $this->assertTrue($period->bucketsByMonth());

        $series = (new \App\Services\DashboardMetrics($period))->revenueSeries()['current'];

        // Six months, six buckets — never a hundred and fifty days of
        // hair.
        $this->assertSame(6, $series->count());
        $this->assertSame(1000, $series->first()['revenue_cents']);
        $this->assertSame(2000, $series->last()['revenue_cents']);
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}$#', $series->first()['label']);
    }

    public function test_a_short_period_still_counts_by_day(): void
    {
        $period = DashboardPeriod::resolve('7d');

        $this->assertFalse($period->bucketsByMonth());
        $this->assertSame(7, (new \App\Services\DashboardMetrics($period))->revenueSeries()['current']->count());
    }

    public function test_the_page_offers_all_time_and_names_its_buckets(): void
    {
        $this->orderAt(now()->subMonths(5)->toDateTimeString(), 1000);

        $this->actingAs($this->admin())->get(route('admin.dashboard', ['period' => 'all']))->assertOk()
            ->assertSee('All time')
            ->assertSee('Orders per month')
            // With no previous window the legend does not announce a series
            // the chart does not draw.
            ->assertDontSee('Previous period');
    }

    /**
     * The pipeline takes the colour the orders list already gives each
     * status. Two sets of tones for the same distinction is one more
     * distinction to learn for nothing.
     */
    public function test_each_stage_wears_the_colour_of_its_status(): void
    {
        $css = file_get_contents(__DIR__.'/../../../public/css/admin.css');
        $base = file_get_contents(__DIR__.'/../../../public/css/base.css');

        // The list's badge and the dashboard's token hold the same hex,
        // status by status.
        foreach ([
            'shipped' => '#3d6b4e',
            'in-transit' => '#6a4a9c',
            'delivered' => '#2f5d8a',
            'preparing' => '#8a6d1f',
        ] as $status => $hex) {
            $this->assertStringContainsString("--status-{$status}: {$hex};", $css, "Dashboard token for {$status}");
            $this->assertStringContainsString($hex, $base, "List badge colour for {$status}");
        }

        // And every stage has a class to carry it.
        foreach (['placed', 'preparing', 'shipped', 'in_transit', 'delivered', 'refunded'] as $status) {
            $this->assertStringContainsString(".dash-status-{$status} {", $css);
        }
    }

    public function test_the_pipeline_counts_refunded_orders(): void
    {
        $this->order(['status' => 'placed']);
        $this->order(['status' => 'refunded']);
        $this->order(['status' => 'refunded']);

        $pipeline = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->pipeline();

        $refunded = $pipeline->firstWhere('status', 'refunded');

        $this->assertSame('Refunded', $refunded['label']);
        $this->assertSame(2, $refunded['count']);
        // Last in the queue: it leaves it, it does not advance it.
        $this->assertSame('refunded', $pipeline->last()['status']);
    }

    public function test_only_open_stages_are_drawn_in_the_bar(): void
    {
        // Delivered and refunded are endings: counted, but out of the bar
        // they would crush as they age — 185 of 211 leaves nothing to see of
        // the four stages still to work.
        $this->order(['status' => 'placed']);
        $this->order(['status' => 'delivered']);
        $this->order(['status' => 'refunded']);

        $pipeline = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->pipeline();

        $this->assertSame(
            ['placed', 'preparing', 'shipped', 'in_transit'],
            $pipeline->where('open', true)->pluck('status')->values()->all()
        );
        $this->assertSame(
            ['delivered', 'refunded'],
            $pipeline->where('open', false)->pluck('status')->values()->all()
        );

        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent();

        // One segment only in the pipeline bar: the placed order.
        $this->assertStringContainsString('dash-stack-segment dash-status-placed', $html);
        $this->assertStringNotContainsString('dash-stack-segment dash-status-delivered', $html);
        $this->assertStringNotContainsString('dash-stack-segment dash-status-refunded', $html);
        // Counted all the same, under the rule.
        $this->assertStringContainsString('dash-swatch dash-status-delivered', $html);
        $this->assertStringContainsString('dash-pipeline-list--closed', $html);
    }

    public function test_an_empty_queue_says_so_instead_of_drawing_a_bar(): void
    {
        $this->order(['status' => 'delivered']);

        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Nothing waiting. Every order is delivered or refunded.', $html);
        $this->assertStringNotContainsString('aria-label="Open orders by stage"', $html);
    }

    public function test_an_archived_refund_stays_out_of_the_pipeline(): void
    {
        // The pipeline is the work queue: archiving files the row away, here
        // as for every other status.
        $this->order(['status' => 'refunded'])->forceFill(['archived_at' => now()])->save();

        $pipeline = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->pipeline();

        $this->assertSame(0, $pipeline->firstWhere('status', 'refunded')['count']);
    }

    public function test_a_banned_account_is_no_longer_counted_as_a_customer(): void
    {
        $banned = User::factory()->create(['banned_at' => now()]);
        $good = User::factory()->create();

        $this->order(['user_id' => $banned->id, 'total_cents' => 9000]);
        $this->order(['user_id' => $good->id, 'total_cents' => 1000]);

        $metrics = new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d'));
        $customers = $metrics->customers();

        // One head fewer everywhere: buyers of the period, buyers of all
        // time, and the average that divides them.
        $this->assertSame(1, $customers['buyers']);
        $this->assertSame(1, $customers['lifetime_buyers']);
        $this->assertSame(1000, $customers['lifetime_value_cents']);

        // Shop accounts are counted on the same rule: lifting the ban gives
        // exactly one head back to both figures.
        $accountsBanned = $metrics->reference()['customers'];
        $newBanned = $metrics->headline()['new_customers'];

        $banned->forceFill(['banned_at' => null])->save();

        $lifted = new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d'));

        $this->assertSame($accountsBanned + 1, $lifted->reference()['customers']);
        $this->assertSame($newBanned + 1, $lifted->headline()['new_customers']);
        $this->assertSame(2, $lifted->customers()['buyers']);
    }

    public function test_a_banned_customers_orders_are_still_revenue(): void
    {
        // That money really was taken: removing it would have the dashboard
        // report less than the orders list for the same period.
        $banned = User::factory()->create(['banned_at' => now()]);
        $this->order(['user_id' => $banned->id, 'total_cents' => 9000]);

        $metrics = new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d'));

        $this->assertSame(9000, $metrics->headline()['revenue_cents']);
        $this->assertSame(9000, $metrics->money()['revenue_cents']);
        $this->assertSame(1, $metrics->headline()['orders']);
    }

    public function test_the_stock_chips_say_how_many_references_are_already_on_order(): void
    {
        // Two shortages, only one restocked: the chip has to say the
        // difference, or it sends the reader to two product pages one of
        // which is waiting on nothing but the postman.
        $coming = Product::factory()->create(['is_active' => true, 'quantity' => 0]);
        Product::factory()->create(['is_active' => true, 'quantity' => 0]);

        $supplier = \App\Models\Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $open = \App\Models\PurchaseOrder::query()->create([
            'number' => 'BC-ONORDER-1', 'supplier_id' => $supplier->id,
            'supplier_name' => 'Fournisseur', 'status' => 'sent',
        ]);
        \App\Models\PurchaseOrderItem::query()->create([
            'purchase_order_id' => $open->id, 'product_id' => $coming->id,
            'name' => $coming->localizedName(), 'sku' => $coming->sku,
            'quantity_ordered' => 10, 'quantity_received' => 0, 'unit_cost_cents' => 500,
        ]);

        $attention = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->attention();
        $chip = $attention->firstWhere('key', 'out-of-stock');

        $this->assertSame(2, $chip['count']);
        $this->assertSame('1 on order', $chip['note']);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('1 on order');
    }

    public function test_a_received_purchase_order_no_longer_counts_as_on_order(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 0]);

        $supplier = \App\Models\Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $closed = \App\Models\PurchaseOrder::query()->create([
            'number' => 'BC-DONE-1', 'supplier_id' => $supplier->id,
            'supplier_name' => 'Fournisseur', 'status' => 'received',
        ]);
        \App\Models\PurchaseOrderItem::query()->create([
            'purchase_order_id' => $closed->id, 'product_id' => $product->id,
            'name' => $product->localizedName(), 'sku' => $product->sku,
            'quantity_ordered' => 10, 'quantity_received' => 10, 'unit_cost_cents' => 500,
        ]);

        $chip = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))
            ->attention()->firstWhere('key', 'out-of-stock');

        // Nothing on its way: the chip carries no empty bracket.
        $this->assertSame(1, $chip['count']);
        $this->assertNull($chip['note']);
    }

    public function test_recent_orders_show_their_units_and_their_channel(): void
    {
        $product = Product::factory()->create();

        $direct = $this->order(['total_cents' => 1500]);
        foreach ([3, 1] as $quantity) {
            OrderItem::query()->create([
                'order_id' => $direct->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
                'name' => ['fr' => 'X'], 'image' => '', 'quantity' => $quantity,
                'unit_price_cents' => 500, 'line_cents' => 500 * $quantity,
            ]);
        }

        $marketplace = \App\Models\Marketplace::query()->create(['name' => 'NaturaBuy']);
        $sold = $this->order([
            'total_cents' => 2000,
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'NaturaBuy',
        ]);
        OrderItem::query()->create([
            'order_id' => $sold->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
            'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 1,
            'unit_price_cents' => 2000, 'line_cents' => 2000,
        ]);

        $orders = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->recentOrders();

        // The units in the box, not the number of references: three targets
        // and one roll make four items to pack.
        $this->assertSame(4, (int) $orders->firstWhere('number', $direct->number)->units_count);
        $this->assertSame(1, (int) $orders->firstWhere('number', $sold->number)->units_count);

        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('4 items', $html);
        // The channel closes the line of facts: the marketplace is named,
        // and a direct sale, having nobody to credit, says so.
        $this->assertMatchesRegularExpression('#dash-order-channel">\s*NaturaBuy#', $html);
        $this->assertMatchesRegularExpression('#dash-order-channel">\s*Direct#', $html);
    }

    public function test_top_products_carry_their_average_unit_price_and_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'CRT-REACT-076']);

        // Two sales of the same article at two prices: the average is
        // revenue per unit, not the price shown today.
        foreach ([[2, 1000], [3, 2100]] as [$quantity, $lineCents]) {
            $order = $this->order(['total_cents' => $lineCents]);
            OrderItem::query()->create([
                'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
                'name' => ['fr' => 'X'], 'image' => '', 'quantity' => $quantity,
                'unit_price_cents' => (int) ($lineCents / $quantity), 'line_cents' => $lineCents,
            ]);
        }

        $row = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->topProducts()->first();

        $this->assertSame(5, $row['quantity']);
        $this->assertSame(3100, $row['revenue_cents']);
        $this->assertSame(620, $row['unit_price_cents']);
        $this->assertSame('CRT-REACT-076', $row['sku']);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('CRT-REACT-076')
            ->assertSee('6,20')
            ->assertSee('Avg sold at')
            ->assertSee('Avg cost');
    }

    public function test_top_products_show_what_the_unit_cost_to_buy(): void
    {
        $product = Product::factory()->create();
        $this->receive($product, 10, 250); // 3,00 € TTC l'unité

        $order = $this->order(['total_cents' => 2000]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
            'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 2,
            'unit_price_cents' => 1000, 'line_cents' => 2000,
        ]);

        $row = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->topProducts()->first();

        $this->assertSame(1000, $row['unit_price_cents']);
        $this->assertSame(300, $row['unit_cost_cents']);
    }

    public function test_a_product_never_purchased_has_no_cost_to_show(): void
    {
        // With no purchase history the cost is unknown, never nothing: a
        // margin read against a zero would be false.
        $product = Product::factory()->create();

        $order = $this->order(['total_cents' => 2000]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
            'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 1,
            'unit_price_cents' => 2000, 'line_cents' => 2000,
        ]);

        $row = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->topProducts()->first();

        $this->assertNull($row['unit_cost_cents']);
    }

    public function test_a_deleted_product_has_no_sku_to_show(): void
    {
        // The sold line keeps the name, never the reference: a deleted
        // product has no SKU left, and a dash is better than inventing
        // one.
        $order = $this->order();
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => null, 'product_slug' => 'gone',
            'name' => ['fr' => 'Article supprimé'], 'image' => '', 'quantity' => 1,
            'unit_price_cents' => 500, 'line_cents' => 500,
        ]);

        $row = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->topProducts()->first();

        $this->assertNull($row['sku']);
        $this->assertSame(500, $row['unit_price_cents']);
    }

    public function test_the_warehouse_values_the_shelves_at_todays_selling_price(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 4, 'price_cents' => 1000]);
        $this->receive($product, 10, 250); // 3,00 € TTC l'unité

        $stock = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->stockValue();

        // 4 × 10,00 € on the shelf against 4 × 3,00 € paid.
        $this->assertSame(4000, $stock['retail_cents']);
        $this->assertSame(1200, $stock['warehouse_cents']);
        $this->assertSame(2800, $stock['shelf_margin_cents']);
        $this->assertSame(233.3, $stock['shelf_markup_percent']);
    }

    public function test_the_shelf_margin_only_covers_references_whose_cost_is_known(): void
    {
        // A selling price always exists, a purchase cost does not: the
        // resale value covers the whole shelf, the margin only the part
        // whose two ends are known.
        $priced = Product::factory()->create(['is_active' => true, 'quantity' => 2, 'price_cents' => 1000]);
        $this->receive($priced, 10, 250);

        Product::factory()->create(['is_active' => true, 'quantity' => 5, 'price_cents' => 4000]);

        $stock = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->stockValue();

        $this->assertSame(22000, $stock['retail_cents']);
        $this->assertSame(2000, $stock['retail_of_valued_cents']);
        $this->assertSame(600, $stock['warehouse_cents']);
        $this->assertSame(1400, $stock['shelf_margin_cents']);
        $this->assertSame(1, $stock['unpriced_references']);
    }

    public function test_an_active_discount_lowers_the_shelf_value(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 3, 'price_cents' => 2000]);

        \App\Models\Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 25,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $stock = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->stockValue();

        // 3 × 15,00 €, the price a customer pays today.
        $this->assertSame(4500, $stock['retail_cents']);
    }
}
