<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrderItem;
use App\Models\ProductSetting;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tout ce que le tableau de bord affiche, pour une période donnée.
 *
 * Le contrôleur résout la période et passe la main : les chiffres, les
 * séries et les classements se calculent ici, contre la même tranche de
 * temps, pour qu'aucun panneau ne puisse regarder ailleurs que les autres.
 *
 * Les ventes couvrent les commandes archivées : archiver range la liste de
 * travail, ça ne défait pas la vente. Seules les commandes de test sortent,
 * puisque ces ventes n'ont jamais eu lieu.
 */
class DashboardMetrics
{
    /** @var Collection<int, int>|null */
    private ?Collection $bannedUserIds = null;

    public function __construct(private readonly DashboardPeriod $period) {}

    /** Commandes qui comptent comme du chiffre d'affaires. */
    private function salesQuery(): Builder
    {
        return Order::query()->excludingTest()->whereNotIn('status', ['refunded', 'draft']);
    }

    /**
     * Banned accounts, out of every head count.
     *
     * Their orders remain sales — that money really was taken, and removing
     * it would have the dashboard report less than the orders list for the
     * same period. It is the person who stops counting, not what they
     * bought.
     *
     * @return Collection<int, int>
     */
    private function bannedUserIds(): Collection
    {
        return $this->bannedUserIds ??= User::query()->whereNotNull('banned_at')->pluck('id');
    }

    private function between(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * The catalogue as it stands, whatever the period: what can be sold,
     * and what sits on the shelves.
     *
     * References count what a customer can actually pick - an active
     * product without declinations, or each active declination of an
     * active product. Stock counts the units on hand behind those same
     * references: an inactive product is off the shelf here too, and only
     * the warehouse counts - what the supplier holds is theirs, not stock.
     *
     * Then what is on its way: the open purchase orders' unreceived lines,
     * split the same way - references not yet for sale (a product or
     * declination still inactive, waiting for its first delivery) and the
     * units the shelves of what is for sale will gain.
     *
     * @return array{products: int, variants: int, references: int, stock_units: int, references_incoming: int, stock_incoming: int}
     */
    public function catalogue(): array
    {
        $activeProducts = Product::query()->where('is_active', true);

        $plainActive = (clone $activeProducts)->whereDoesntHave('variants')->count();
        $activeVariants = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->count();

        // One pass over what is still awaited, sorted by whether the
        // reference it feeds is for sale today.
        $awaited = PurchaseOrderItem::query()
            ->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->whereHas('purchaseOrder', fn ($query) => $query->open())
            ->with(['product', 'variant'])
            ->get()
            ->filter(fn (PurchaseOrderItem $item): bool => $item->product !== null);

        $forSale = fn (PurchaseOrderItem $item): bool => $item->product->is_active
            && ($item->variant === null || $item->variant->is_active);

        return [
            'products' => (clone $activeProducts)->count(),
            'variants' => $activeVariants,
            'references' => $plainActive + $activeVariants,
            'stock_units' => (int) (clone $activeProducts)->whereDoesntHave('variants')->sum('quantity')
                + (int) ProductVariant::query()
                    ->where('is_active', true)
                    ->whereHas('product', fn ($query) => $query->where('is_active', true))
                    ->sum('quantity'),
            'references_incoming' => $awaited
                ->reject($forSale)
                ->unique(fn (PurchaseOrderItem $item): string => $item->product_id.'-'.($item->product_variant_id ?? 0))
                ->count(),
            'stock_incoming' => (int) $awaited
                ->filter($forSale)
                ->sum(fn (PurchaseOrderItem $item): int => $item->quantityRemaining()),
        ];
    }

    /**
     * Les chiffres d'en-tête, chacun avec son écart contre la tranche
     * précédente.
     *
     * @return array<string, mixed>
     */
    public function headline(): array
    {
        $current = $this->windowTotals($this->period->start, $this->period->end);
        $previous = $this->windowTotals($this->period->previousStart, $this->period->previousEnd);

        return [
            'revenue_cents' => $current['revenue_cents'],
            'revenue_delta' => $this->delta($current['revenue_cents'], $previous['revenue_cents']),
            'orders' => $current['orders'],
            'orders_delta' => $this->delta($current['orders'], $previous['orders']),
            'average_order_cents' => $current['orders'] > 0
                ? (int) round($current['revenue_cents'] / $current['orders'])
                : 0,
            'average_order_delta' => $this->delta(
                $current['orders'] > 0 ? (int) round($current['revenue_cents'] / $current['orders']) : 0,
                $previous['orders'] > 0 ? (int) round($previous['revenue_cents'] / $previous['orders']) : 0,
            ),
            'refunded_cents' => $current['refunded_cents'],
            'refunded_delta' => $this->delta($current['refunded_cents'], $previous['refunded_cents']),
            'new_customers' => $current['new_customers'],
            'new_customers_delta' => $this->delta($current['new_customers'], $previous['new_customers']),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function windowTotals(Carbon $start, Carbon $end): array
    {
        $sales = $this->between($this->salesQuery(), $start, $end)
            ->selectRaw('count(*) as orders, coalesce(sum(total_cents), 0) as revenue_cents')
            ->first();

        return [
            'orders' => (int) $sales->orders,
            'revenue_cents' => (int) $sales->revenue_cents,
            'refunded_cents' => (int) $this->between(
                Order::query()->excludingTest()->where('status', 'refunded'), $start, $end
            )->sum('total_cents'),
            'new_customers' => (int) $this->between(
                User::query()->where('is_admin', false)->where('external', false)->whereNull('banned_at'),
                $start,
                $end,
            )->count(),
        ];
    }

    /**
     * L'écart entre deux valeurs, en pourcentage signé.
     *
     * Null quand la tranche précédente est vide : une croissance depuis zéro
     * n'a pas de pourcentage, et afficher « +∞ % » ou planter sont deux
     * mauvaises réponses à la même question.
     *
     * @return array{percent: float|null, direction: string, from: int}
     */
    private function delta(int $current, int $previous): array
    {
        if ($previous === 0) {
            return ['percent' => null, 'direction' => $current > 0 ? 'up' : 'flat', 'from' => $previous];
        }

        $percent = round(($current - $previous) / abs($previous) * 100, 1);

        return [
            'percent' => $percent,
            'direction' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'flat'),
            'from' => $previous,
        ];
    }

    /**
     * What the period brought in and what it kept, written as the
     * subtraction it is: revenue, the costs deducted from it, what the
     * goods cost, and the profit left over.
     *
     * The costs are the ones already recorded against each order — own
     * shipping, marketplace commission, payment fees — the same three the
     * orders list totals. The goods are priced at their average purchase
     * cost including VAT, and an order holding a line that cannot be
     * priced is left out of the profit entirely rather than counted at
     * zero: the counter says how many that is.
     *
     * @return array<string, mixed>
     */
    public function money(): array
    {
        $current = $this->windowMoney($this->period->start, $this->period->end);
        $previous = $this->windowMoney($this->period->previousStart, $this->period->previousEnd);

        return [
            ...$current,
            'profit_delta' => $this->delta($current['profit_cents'], $previous['profit_cents']),
            'costs_delta' => $this->delta($current['order_costs_cents'], $previous['order_costs_cents']),
            'goods_delta' => $this->delta($current['product_cost_cents'], $previous['product_cost_cents']),
            'previous_margin_percent' => $previous['margin_percent'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function windowMoney(Carbon $start, Carbon $end): array
    {
        $scope = fn (): Builder => $this->between($this->salesQuery(), $start, $end);

        $recorded = $scope()->selectRaw(
            'coalesce(sum(total_cents), 0) as revenue_cents,'
            .' coalesce(sum(shipping_paid_cents), 0) as shipping_cents,'
            .' coalesce(sum(marketplace_commission_cents), 0) as commission_cents,'
            .' coalesce(sum(payment_fee_cents), 0) as fee_cents,'
            .' count(*) as orders'
        )->first();

        $orders = $scope()->with('items')->get();

        $costsByProductId = Product::averagePurchaseCostsInclVatCents(
            $orders->flatMap(fn (Order $order) => $order->items->pluck('product_id'))->filter(),
        );

        $priced = $orders->filter(
            fn (Order $order): bool => $order->profitInclVatCents($costsByProductId) !== null,
        );

        $productCostCents = (int) $priced->sum(
            fn (Order $order): int => $order->productCostInclVatCents($costsByProductId),
        );
        // The costs of the priced orders only: the bar sets them against
        // their own revenue, and mixing the two perimeters would give shares
        // that add up to nothing.
        $pricedCostsCents = (int) $priced->sum(
            fn (Order $order): int => (int) $order->shipping_paid_cents
                + (int) $order->marketplace_commission_cents
                + (int) $order->payment_fee_cents,
        );
        $profitCents = (int) $priced->sum(
            fn (Order $order): int => $order->profitInclVatCents($costsByProductId),
        );
        // The margin is a share of the priced orders' revenue: against the
        // total it would mix a partial profit with sales it does not
        // cover.
        $pricedRevenueCents = (int) $priced->sum('total_cents');

        $orderCostsCents = (int) $recorded->shipping_cents
            + (int) $recorded->commission_cents
            + (int) $recorded->fee_cents;

        return [
            'revenue_cents' => (int) $recorded->revenue_cents,
            'shipping_cents' => (int) $recorded->shipping_cents,
            'commission_cents' => (int) $recorded->commission_cents,
            'fee_cents' => (int) $recorded->fee_cents,
            'order_costs_cents' => $orderCostsCents,
            'product_cost_cents' => $productCostCents,
            'profit_cents' => $profitCents,
            'priced_revenue_cents' => $pricedRevenueCents,
            'priced_costs_cents' => $pricedCostsCents,
            'margin_percent' => $pricedRevenueCents > 0
                ? round($profitCents / $pricedRevenueCents * 100, 1)
                : null,
            'markup_percent' => $productCostCents > 0
                ? round($profitCents / $productCostCents * 100, 1)
                : null,
            'priced_orders' => $priced->count(),
            'total_orders' => (int) $recorded->orders,
        ];
    }

    /**
     * What the shelves are worth at purchase cost, and what is committed
     * on top. A reference with no purchase history has no known value: it is
     * counted separately rather than at zero, or the total falls as the
     * catalogue grows.
     *
     * @return array<string, int|null>
     */
    public function stockValue(): array
    {
        $products = Product::query()
            ->where('is_active', true)
            // A discount is part of today's price: the resale value is what
            // the shelves would fetch if they sold now, not what they would
            // fetch at full price.
            ->with(['variants', 'discount'])
            ->get(['id', 'quantity', 'price_cents']);

        $costs = Product::averagePurchaseCostsInclVatCents($products->pluck('id'));

        $valued = 0;
        $retail = 0;
        $retailOfValued = 0;
        $units = 0;
        $unpriced = 0;

        foreach ($products as $product) {
            // Same rule as catalogue(): as soon as a product has
            // declinations the units are theirs and the product's own column
            // does not count. Adding it counted twice a stock the "Units in
            // stock" tile right beside it counted once.
            $activeVariants = $product->variants->where('is_active', true);

            $onHand = $product->variants->isEmpty()
                ? (int) $product->quantity
                : (int) $activeVariants->sum('quantity');

            // A selling price, on the other hand, is always known: the
            // resale value therefore covers the whole shelf, including what
            // has no purchase cost.
            $onShelfRetail = $product->variants->isEmpty()
                ? $onHand * $product->effectivePriceCents()
                : (int) $activeVariants->sum(
                    fn (ProductVariant $variant): int => $variant->quantity * $variant->effectivePriceCents(),
                );

            $retail += $onShelfRetail;

            if (! array_key_exists($product->id, $costs)) {
                $unpriced += $onHand > 0 ? 1 : 0;

                continue;
            }

            $valued += $onHand * $costs[$product->id];
            // The margin can only be read where both ends are known: taking
            // a partial cost off a complete price would claim a profit the
            // shelves do not carry.
            $retailOfValued += $onShelfRetail;
            $units += $onHand;
        }

        // What is ordered and not yet received, at the purchase order's own
        // price rather than the average: that money is already committed at
        // the rate written on it.
        $openOrders = PurchaseOrder::query()->open()->with('items')->get();

        $committed = (int) $openOrders->sum(
            fn (PurchaseOrder $order): int => $order->withVatCents((int) $order->items->sum(
                fn (PurchaseOrderItem $item): int => $item->unit_cost_cents * $item->quantityRemaining(),
            )),
        );

        return [
            'warehouse_cents' => $valued,
            'retail_cents' => $retail,
            'retail_of_valued_cents' => $retailOfValued,
            'shelf_margin_cents' => $retailOfValued - $valued,
            'shelf_markup_percent' => $valued > 0
                ? round(($retailOfValued - $valued) / $valued * 100, 1)
                : null,
            'valued_units' => $units,
            'unpriced_references' => $unpriced,
            'committed_cents' => $committed,
            'open_purchase_orders' => $openOrders->count(),
        ];
    }

    /**
     * Who buys: the period's newcomers, those who come back, and what a
     * customer is worth on average since the beginning.
     *
     * "Returning" means they had already ordered before the period, not that
     * they ordered twice inside it: what is being looked at is loyalty, not
     * cadence.
     *
     * @return array<string, mixed>
     */
    public function customers(): array
    {
        // This panel counts people: a banned account is no longer one. The
        // average spent therefore drops their orders too, or it would divide
        // everyone's money by the crowd that remains.
        $counted = fn (Builder $query): Builder => $query
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $this->bannedUserIds());

        $buyerIds = $counted($this->between($this->salesQuery(), $this->period->start, $this->period->end))
            ->distinct()
            ->pluck('user_id');

        $returningIds = $buyerIds->isEmpty()
            ? collect()
            : $this->salesQuery()
                ->whereIn('user_id', $buyerIds)
                ->where('created_at', '<', $this->period->start)
                ->distinct()
                ->pluck('user_id');

        $lifetime = $counted($this->salesQuery())
            ->selectRaw('count(distinct user_id) as buyers, coalesce(sum(total_cents), 0) as revenue_cents, count(*) as orders')
            ->first();

        $buyers = (int) $lifetime->buyers;

        $repeatBuyers = $counted($this->salesQuery())
            ->selectRaw('user_id')
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        return [
            'buyers' => $buyerIds->count(),
            'returning' => $returningIds->count(),
            'new' => $buyerIds->count() - $returningIds->count(),
            'returning_percent' => $buyerIds->count() > 0
                ? round($returningIds->count() / $buyerIds->count() * 100, 1)
                : null,
            'lifetime_buyers' => $buyers,
            'lifetime_value_cents' => $buyers > 0 ? (int) round((int) $lifetime->revenue_cents / $buyers) : 0,
            'orders_per_buyer' => $buyers > 0 ? round((int) $lifetime->orders / $buyers, 2) : null,
            'repeat_buyers' => $repeatBuyers,
            'repeat_percent' => $buyers > 0 ? round($repeatBuyers / $buyers * 100, 1) : null,
        ];
    }

    /**
     * Le chiffre d'affaires jour par jour, période courante et précédente.
     *
     * Une seule requête par tranche, ventilée en PHP : strftime() ne parle
     * qu'à SQLite, et remplir les jours sans vente — que le graphique exige —
     * est de toute façon plus simple ici.
     *
     * @return array<string, mixed>
     */
    public function revenueSeries(): array
    {
        return [
            'current' => $this->dailyBuckets($this->period->start, $this->period->end),
            'previous' => $this->dailyBuckets($this->period->previousStart, $this->period->previousEnd),
        ];
    }

    /**
     * A bucket per day, or per month when the window is long: past four
     * months, a point per day is hair rather than a line, and its table twin
     * has a row for every one of them.
     *
     * @return Collection<int, array{date: Carbon, label: string, revenue_cents: int, orders: int}>
     */
    private function dailyBuckets(Carbon $start, Carbon $end): Collection
    {
        // "All time" has no previous window: the one handed over then ends
        // before it begins, and there is no bucket to fill.
        if ($end->lessThan($start)) {
            return collect();
        }

        $byMonth = $this->period->bucketsByMonth();
        $key = fn (Carbon $date): string => $byMonth ? $date->format('Y-m') : $date->format('Y-m-d');

        $rows = $this->between($this->salesQuery(), $start, $end)
            ->get(['created_at', 'total_cents'])
            ->groupBy(fn (Order $order): string => $key($order->created_at));

        $cursor = $byMonth ? $start->copy()->startOfMonth() : $start->copy()->startOfDay();
        $last = $byMonth ? $end->copy()->startOfMonth() : $end->copy()->startOfDay();

        $buckets = collect();

        while ($cursor->lessThanOrEqualTo($last)) {
            $bucket = $rows->get($key($cursor));

            $buckets->push([
                'date' => $cursor->copy(),
                'label' => $cursor->format($byMonth ? 'm/y' : 'd/m'),
                'revenue_cents' => (int) ($bucket?->sum('total_cents') ?? 0),
                'orders' => (int) ($bucket?->count() ?? 0),
            ]);

            $byMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * Douze points de tendance pour les tuiles, quelle que soit la longueur
     * de la période : une courbe décorative n'a pas besoin d'un point par
     * jour, et douze se lisent à n'importe quelle largeur.
     *
     * @return array<string, array<int, int>>
     */
    public function sparklines(): array
    {
        $buckets = $this->dailyBuckets($this->period->start, $this->period->end);
        $chunkSize = max(1, (int) ceil($buckets->count() / 12));


        $revenue = $buckets->chunk($chunkSize)
            ->map(fn (Collection $chunk): int => (int) $chunk->sum('revenue_cents'))->values();
        $orders = $buckets->chunk($chunkSize)
            ->map(fn (Collection $chunk): int => (int) $chunk->sum('orders'))->values();

        return ['revenue' => $revenue->all(), 'orders' => $orders->all()];
    }

    /**
     * Les cinq produits les plus vendus de la période.
     *
     * Agrégé par la base puis chargé pour ces cinq-là seulement : la version
     * précédente ramenait en PHP chaque ligne de commande jamais vendue,
     * puis chargeait un produit par référence distincte — avant même de
     * prendre les cinq premiers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topProducts(int $limit = 5): Collection
    {
        // Groupé par référence et non par product_id : celui-ci passe à null
        // quand le produit est supprimé, et la ligne vendue doit rester
        // lisible — c'est elle qui garde le nom figé à la vente.
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.test_marked_at')
            ->whereNotIn('orders.status', ['refunded', 'draft'])
            ->whereBetween('orders.created_at', [$this->period->start, $this->period->end])
            ->groupBy('order_items.product_slug')
            ->orderByRaw('sum(order_items.quantity) desc')
            ->limit($limit)
            ->get([
                'order_items.product_slug',
                DB::raw('max(order_items.id) as sample_item_id'),
                DB::raw('sum(order_items.quantity) as quantity'),
                DB::raw('sum(order_items.line_cents) as revenue_cents'),
            ]);

        // Une requête de plus, bornée à ces cinq lignes : de quoi lire le nom
        // figé et rejoindre la fiche produit quand elle existe encore.
        $samples = OrderItem::query()
            ->whereIn('id', $rows->pluck('sample_item_id'))
            ->with('product')
            ->get()
            ->keyBy('id');

        // And the purchase cost of those five, in one go: what the unit
        // costs against what it brings in.
        $costs = Product::averagePurchaseCostsInclVatCents(
            $samples->pluck('product_id')->filter(),
        );

        return $rows->map(function ($row) use ($samples, $costs): array {
            $sample = $samples->get($row->sample_item_id);

            $quantity = (int) $row->quantity;
            $revenue = (int) $row->revenue_cents;

            return [
                'product' => $sample?->product,
                'name' => $sample?->localizedName() ?? 'Produit supprimé',
                // The reference comes from the product record, not the sold
                // line: that keeps none, so a deleted product has no SKU
                // left to show.
                'sku' => $sample?->product?->sku,
                'quantity' => $quantity,
                'revenue_cents' => $revenue,
                // What the unit sold for on average: today's list price does
                // not say what it went for, discounts and marketplace prices
                // included.
                'unit_price_cents' => $quantity > 0 ? (int) round($revenue / $quantity) : null,
                // Absent rather than zero when nothing has been received: an
                // unknown cost is not a cost of nothing, and the margin read
                // against it would be false.
                'unit_cost_cents' => $costs[$sample?->product_id] ?? null,
            ];
        })->values();
    }

    /**
     * La répartition par canal de vente, plafonnée : au-delà de trois
     * segments les couleurs cessent de se distinguer, donc la traîne se
     * replie sur « Other » plutôt que d'inventer une teinte de plus.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function channelSplit(int $limit = 3): Collection
    {
        $rows = $this->between($this->salesQuery(), $this->period->start, $this->period->end)
            ->selectRaw("coalesce(nullif(marketplace_name, ''), 'Direct sale') as label, count(*) as orders, sum(total_cents) as revenue_cents, coalesce(sum(marketplace_commission_cents), 0) as commission_cents")
            ->groupBy('label')
            ->orderByDesc('revenue_cents')
            ->get();

        // The commission is not a detail of the channel, it is its price: a
        // channel that sells more and hands more back is not the better one,
        // and only the net column says so.
        $line = fn (string $label, int $orders, int $revenue, int $commission): array => [
            'label' => $label,
            'orders' => $orders,
            'revenue_cents' => $revenue,
            'commission_cents' => $commission,
            'net_cents' => $revenue - $commission,
        ];

        $head = $rows->take($limit)->map(fn ($row): array => $line(
            $row->label,
            (int) $row->orders,
            (int) $row->revenue_cents,
            (int) $row->commission_cents,
        ));

        $tail = $rows->skip($limit);

        if ($tail->isNotEmpty()) {
            $head->push($line(
                'Other',
                (int) $tail->sum('orders'),
                (int) $tail->sum('revenue_cents'),
                (int) $tail->sum('commission_cents'),
            ));
        }

        return $head->values();
    }

    /**
     * The pipeline of orders in hand. Each stage wears the colour the
     * orders list already gives that status: it is the distinction the eye
     * learned there, and repeating it here saves teaching a second one for
     * the same thing.
     *
     * @return Collection<int, array{status: string, label: string, count: int, open: bool}>
     */
    public function pipeline(): Collection
    {
        // Refunded is not one more stage, it is the way out: it closes the
        // row instead of advancing it. It is counted here all the same,
        // because an order that has left the pipeline is still an order the
        // pipeline has to account for.
        $statuses = ['placed', 'preparing', 'shipped', 'in_transit', 'delivered', 'refunded'];

        $counts = Order::query()
            ->whereNull('archived_at')
            ->excludingTest()
            ->whereIn('status', $statuses)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Open means there is something left to do. Delivered and refunded
        // are endings: they are counted, but they take no room in the bar,
        // or the only thing it would show, as the shop ages, is how many
        // orders are already finished.
        $open = ['placed' => 'Placed', 'preparing' => 'Preparing', 'shipped' => 'Shipped', 'in_transit' => 'In transit'];
        $closed = ['delivered' => 'Delivered', 'refunded' => 'Refunded'];

        return collect([...$open, ...$closed])
            ->map(fn (string $label, string $status): array => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
                'open' => array_key_exists($status, $open),
            ])->values();
    }

    /**
     * Ce qui demande une action maintenant. Rien ici quand tout est en
     * ordre : une bande d'alerte toujours pleine apprend à l'ignorer.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function attention(): Collection
    {
        $openOrders = fn (): Builder => Order::query()->whereNull('archived_at')->excludingTest();

        $toPrepare = (clone $openOrders())->whereIn('status', ['placed', 'preparing'])->count();
        $missingTracking = (clone $openOrders())->whereIn('status', ['shipped', 'in_transit'])
            ->where(fn ($query) => $query->whereNull('tracking_number')->orWhere('tracking_number', ''))
            ->count();
        $unreadMessages = Conversation::query()->unreadForAdmin()->count();
        $purchaseOrders = PurchaseOrder::query()->awaitingReceipt()->count();
        $overduePurchaseOrders = PurchaseOrder::query()->awaitingReceipt()
            ->whereNotNull('expected_at')->whereDate('expected_at', '<', now())->count();
        $outOfStockQuery = fn (): Builder => Product::query()->active()->where('quantity', '<=', 0);
        $lowStockQuery = fn (): Builder => Product::query()->active()
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', ProductSetting::lowStockThreshold());

        $outOfStock = $outOfStockQuery()->count();
        $lowStock = $lowStockQuery()->count();

        // How many of these references are already on order. A shortage
        // whose restock has left does not call for the same thing as one
        // nobody has handled, and the chip did not say the difference: it
        // sent the reader to sixty-three product pages of which twelve were
        // waiting on nothing but the postman.
        $awaited = fn (Builder $line) => $line
            ->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->whereHas('purchaseOrder', fn (Builder $order) => $order->open());

        // The product's own purchase lines or its declinations': that is
        // where a declined product's restock lives, as scopeNotOutOfStock
        // already reads it.
        $onOrder = fn (Builder $query): int => (clone $query)
            ->where(fn (Builder $inner) => $inner
                ->whereHas('purchaseOrderItems', $awaited)
                ->orWhereHas('variants.purchaseOrderItems', $awaited))
            ->count();

        $outOfStockOnOrder = $onOrder($outOfStockQuery());
        $lowStockOnOrder = $onOrder($lowStockQuery());

        return collect([
            [
                'key' => 'to-prepare',
                'count' => $toPrepare,
                'label' => 'to prepare',
                'level' => 'warning',
                'url' => route('admin.orders.index', ['status' => 'placed']),
            ],
            [
                'key' => 'missing-tracking',
                'count' => $missingTracking,
                'label' => 'missing tracking',
                'level' => 'serious',
                // Sans filtre de statut : le compte couvre désormais deux
                // statuts et le filtre de la liste n'en accepte qu'un. Y
                // renvoyer vers « shipped » afficherait moins de lignes que
                // la puce n'en annonce.
                'url' => route('admin.orders.index'),
            ],
            [
                'key' => 'unread-messages',
                'count' => $unreadMessages,
                'label' => 'unread '.str('message')->plural($unreadMessages),
                'level' => 'warning',
                'url' => route('admin.conversations.index'),
            ],
            [
                'key' => 'purchase-orders',
                'count' => $purchaseOrders,
                'label' => $overduePurchaseOrders > 0
                    ? 'to receive ('.$overduePurchaseOrders.' overdue)'
                    : 'to receive',
                'level' => $overduePurchaseOrders > 0 ? 'serious' : 'warning',
                'url' => route('admin.purchase-orders.index', ['tab' => 'open']),
            ],
            [
                'key' => 'out-of-stock',
                'count' => $outOfStock,
                'label' => 'out of stock',
                'note' => $outOfStockOnOrder > 0 ? $outOfStockOnOrder.' on order' : null,
                'level' => 'critical',
                'url' => route('admin.products.index', ['tab' => 'out-of-stock', 'sort' => 'stock-asc']),
            ],
            [
                'key' => 'low-stock',
                'count' => $lowStock,
                'label' => 'low on stock',
                'note' => $lowStockOnOrder > 0 ? $lowStockOnOrder.' on order' : null,
                'level' => 'warning',
                'url' => route('admin.products.index', ['tab' => 'in-stock', 'sort' => 'stock-asc']),
            ],
        ])->filter(fn (array $item): bool => $item['count'] > 0)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function bestCustomers(int $limit = 5): Collection
    {
        return $this->between($this->salesQuery(), $this->period->start, $this->period->end)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as orders, sum(total_cents) as revenue_cents')
            ->groupBy('user_id')
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->with('user')
            ->get()
            ->map(fn (Order $row): array => [
                'user' => $row->user,
                'name' => $row->user?->name ?? 'Deleted customer',
                'orders' => (int) $row->orders,
                'revenue_cents' => (int) $row->revenue_cents,
            ]);
    }

    /**
     * @return Collection<int, StockMovement>
     */
    public function recentStockMovements(int $limit = 6): Collection
    {
        return StockMovement::query()
            ->with(['product', 'user'])
            ->whereBetween('created_at', [$this->period->start, $this->period->end])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    public function recentOrders(int $limit = 6): Collection
    {
        return Order::query()
            ->whereNull('archived_at')
            ->excludingTest()
            ->where('status', '!=', 'draft')
            // The marketplace logo and the item count: two more queries in
            // total, not two per row.
            ->with(['user', 'marketplace'])
            ->withSum('items as units_count', 'quantity')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Les produits qu'il faut réapprovisionner, avec de quoi le faire sur
     * place : c'est le pendant actionnable de la puce d'alerte, qui ne fait
     * que signaler.
     *
     * @return Collection<int, Product>
     */
    public function stockAlertProducts(int $limit = 6): Collection
    {
        return Product::query()
            ->active()
            ->where('quantity', '<=', ProductSetting::lowStockThreshold())
            ->orderBy('quantity')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Les chiffres de référence : on les consulte, ils ne signalent rien.
     *
     * @return array<string, int>
     */
    public function reference(): array
    {
        return [
            'customers' => User::query()->where('is_admin', false)->where('external', false)->whereNull('banned_at')->count(),
            'products' => Product::query()->count(),
            'active_products' => Product::query()->active()->count(),
            'drafts' => Order::query()->whereNull('archived_at')->excludingTest()->where('status', 'draft')->count(),
            'external_orders' => Order::query()->whereNull('archived_at')->excludingTest()->where('is_manual', true)->count(),
        ];
    }
}
