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

        // Les quatre chiffres vivent dans le panneau « Warehouse » depuis
        // qu'ils y ont rejoint la valeur du stock : mêmes comptes, même
        // règle, une bande de tuiles en moins.
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

        // 15,00 € encaissés, 3,00 € de frais, 2 unités à 1,20 € :
        // 15,00 − 3,00 − 2,40 = 9,60 € de bénéfice.
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
        // Aucun bon de commande derrière ce produit : le bénéfice de cette
        // vente est inconnu, pas nul. Elle compte dans le chiffre d'affaires
        // et le compteur dit qu'elle manque au reste.
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
        // Sans commande chiffrée, il n'y a pas de marge à écrire.
        $this->assertNull($money['margin_percent']);
    }

    public function test_the_ledger_bar_keeps_one_base_for_its_three_shares(): void
    {
        // Deux ventes, une seule chiffrable : la barre se rapporte au chiffre
        // d'affaires de celle-là, donc ses trois parts totalisent 100 %.
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
        // Les frais de la seule commande chiffrée, pas des deux.
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

        // Une référence en stock sans historique d'achat n'a pas de valeur
        // connue : comptée à part, jamais à zéro.
        Product::factory()->create(['is_active' => true, 'quantity' => 7]);

        $stock = (new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d')))->stockValue();

        $this->assertSame(1200, $stock['warehouse_cents']);
        $this->assertSame(4, $stock['valued_units']);
        $this->assertSame(1, $stock['unpriced_references']);
    }

    public function test_a_product_with_declinations_is_valued_on_its_declinations(): void
    {
        // La colonne du produit reste renseignée derrière ses déclinaisons.
        // C'est celle des déclinaisons qui fait foi — la tuile « Units in
        // stock » compte comme cela, et deux chiffres du même panneau ne
        // peuvent pas compter le même stock deux fois.
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

        // 5 unités déclinées à 3,00 €, jamais 14.
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

        // Six unités encore dues à 5,00 € HT, TVA 20 % : 36,00 €.
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
        // Deux commandes pour le fidèle, une pour l'autre : un seul client
        // sur deux a acheté plus d'une fois.
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
        // Une vente directe ne paie de commission à personne.
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
            // La barre de la ligne de compte et ses trois segments.
            ->assertSee('dash-ledger-segment is-profit', false);
    }

    public function test_all_time_starts_at_the_first_sale(): void
    {
        // Une commande de test n'a jamais eu lieu : elle ne peut pas ouvrir
        // la période, sans quoi la page commencerait sur des mois vides.
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

        // La fenêtre précédente se termine avant de commencer : elle est
        // vide, et l'écart n'a donc pas de référent.
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

        // Six mois, six cases — jamais cent cinquante jours de cheveux.
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
            // Sans tranche précédente, la légende n'annonce pas une série
            // que le graphique ne trace pas.
            ->assertDontSee('Previous period');
    }

    /**
     * Le tuyau reprend la couleur que la liste des commandes donne déjà à
     * chaque statut. Deux jeux de teintes pour la même distinction, c'est
     * une distinction de plus à apprendre pour rien.
     */
    public function test_each_stage_wears_the_colour_of_its_status(): void
    {
        $css = file_get_contents(__DIR__.'/../../../public/css/admin.css');
        $base = file_get_contents(__DIR__.'/../../../public/css/base.css');

        // La pastille de la liste et le jeton du tableau de bord tiennent le
        // même hexadécimal, statut par statut.
        foreach ([
            'shipped' => '#3d6b4e',
            'in-transit' => '#6a4a9c',
            'delivered' => '#2f5d8a',
            'preparing' => '#8a6d1f',
        ] as $status => $hex) {
            $this->assertStringContainsString("--status-{$status}: {$hex};", $css, "Dashboard token for {$status}");
            $this->assertStringContainsString($hex, $base, "List badge colour for {$status}");
        }

        // Et chaque étape a bien une classe pour la porter.
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
        // Dernière de la file : elle en sort, elle ne l'avance pas.
        $this->assertSame('refunded', $pipeline->last()['status']);
    }

    public function test_only_open_stages_are_drawn_in_the_bar(): void
    {
        // Livrées et remboursées sont des fins : comptées, mais hors de la
        // barre, qu'elles écraseraient en vieillissant — 185 sur 211 ne
        // laissent rien à voir aux quatre étapes qui restent à traiter.
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

        // Un seul segment dans la barre du tuyau : la commande placée.
        $this->assertStringContainsString('dash-stack-segment dash-status-placed', $html);
        $this->assertStringNotContainsString('dash-stack-segment dash-status-delivered', $html);
        $this->assertStringNotContainsString('dash-stack-segment dash-status-refunded', $html);
        // Comptées quand même, sous le filet.
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
        // Le tuyau est la file de travail : archiver range la ligne, ici
        // comme pour les autres statuts.
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

        // Une tête en moins partout : acheteurs de la période, acheteurs de
        // toujours, et la moyenne qui les divise.
        $this->assertSame(1, $customers['buyers']);
        $this->assertSame(1, $customers['lifetime_buyers']);
        $this->assertSame(1000, $customers['lifetime_value_cents']);

        // Les comptes ouverts se comptent sur la même règle : lever le
        // bannissement rend exactement une tête aux deux chiffres.
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
        // L'argent a bien été encaissé : le retirer ferait dire au tableau
        // de bord moins que la liste des commandes pour la même période.
        $banned = User::factory()->create(['banned_at' => now()]);
        $this->order(['user_id' => $banned->id, 'total_cents' => 9000]);

        $metrics = new \App\Services\DashboardMetrics(DashboardPeriod::resolve('30d'));

        $this->assertSame(9000, $metrics->headline()['revenue_cents']);
        $this->assertSame(9000, $metrics->money()['revenue_cents']);
        $this->assertSame(1, $metrics->headline()['orders']);
    }
}
