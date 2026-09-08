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
     * Les comptes bannis, hors de tout dénombrement de têtes.
     *
     * Leurs commandes restent des ventes — l'argent a bien été encaissé, et
     * les retirer ferait dire au tableau de bord moins que la liste des
     * commandes pour la même période. C'est la personne qui ne compte plus,
     * pas ce qu'elle a acheté.
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
        // Les frais des seules commandes chiffrées : la barre les met en
        // regard de leur propre chiffre d'affaires, et mélanger les deux
        // périmètres ferait des parts qui ne totalisent rien.
        $pricedCostsCents = (int) $priced->sum(
            fn (Order $order): int => (int) $order->shipping_paid_cents
                + (int) $order->marketplace_commission_cents
                + (int) $order->payment_fee_cents,
        );
        $profitCents = (int) $priced->sum(
            fn (Order $order): int => $order->profitInclVatCents($costsByProductId),
        );
        // La marge se rapporte au chiffre d'affaires des seules commandes
        // chiffrées : rapportée au total, elle mélangerait un profit partiel
        // à des ventes qu'il ne couvre pas.
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
     * Ce que les rayons valent au prix d'achat, et ce qui est engagé
     * dessus. Une référence sans historique d'achat n'a pas de valeur
     * connue : elle est comptée à part plutôt qu'à zéro, sinon le total
     * baisse quand le catalogue grandit.
     *
     * @return array<string, int|null>
     */
    public function stockValue(): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('variants')
            ->get(['id', 'quantity']);

        $costs = Product::averagePurchaseCostsInclVatCents($products->pluck('id'));

        $valued = 0;
        $units = 0;
        $unpriced = 0;

        foreach ($products as $product) {
            // Même règle que catalogue() : dès qu'un produit a des
            // déclinaisons, les unités sont les leurs et la colonne du
            // produit ne compte pas. L'additionner comptait deux fois un
            // stock que la tuile « Units in stock », juste à côté, ne
            // comptait qu'une.
            $activeVariants = $product->variants->where('is_active', true);

            $onHand = $product->variants->isEmpty()
                ? (int) $product->quantity
                : (int) $activeVariants->sum('quantity');

            if (! array_key_exists($product->id, $costs)) {
                $unpriced += $onHand > 0 ? 1 : 0;

                continue;
            }

            $valued += $onHand * $costs[$product->id];
            $units += $onHand;
        }

        // Ce qui est commandé et pas encore reçu, au prix du bon de commande
        // plutôt qu'à la moyenne : cet argent-là est déjà engagé au tarif
        // qui figure dessus.
        $openOrders = PurchaseOrder::query()->open()->with('items')->get();

        $committed = (int) $openOrders->sum(
            fn (PurchaseOrder $order): int => $order->withVatCents((int) $order->items->sum(
                fn (PurchaseOrderItem $item): int => $item->unit_cost_cents * $item->quantityRemaining(),
            )),
        );

        return [
            'warehouse_cents' => $valued,
            'valued_units' => $units,
            'unpriced_references' => $unpriced,
            'committed_cents' => $committed,
            'open_purchase_orders' => $openOrders->count(),
        ];
    }

    /**
     * Qui achète : les nouveaux venus de la période, ceux qui reviennent,
     * et ce qu'un client vaut en moyenne depuis le début.
     *
     * « Revenu » veut dire qu'il avait déjà commandé avant la période, pas
     * qu'il a commandé deux fois dedans : c'est la fidélité qu'on regarde,
     * pas la cadence.
     *
     * @return array<string, mixed>
     */
    public function customers(): array
    {
        // Ce panneau compte des personnes : un compte banni n'en est plus
        // une. La moyenne dépensée écarte donc aussi ses commandes, sans
        // quoi elle diviserait l'argent de tous par la foule qui reste.
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
     * Une case par jour, ou par mois quand la tranche est longue : au-delà
     * de quatre mois, un point par jour donne des cheveux serrés qu'on ne
     * lit plus et un tableau jumeau d'autant de lignes que de jours.
     *
     * @return Collection<int, array{date: Carbon, label: string, revenue_cents: int, orders: int}>
     */
    private function dailyBuckets(Carbon $start, Carbon $end): Collection
    {
        // « Depuis le début » n'a pas de tranche précédente : la fenêtre
        // qu'on nous passe alors se termine avant de commencer, et il n'y a
        // pas de case à remplir.
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

        return $rows->map(function ($row) use ($samples): array {
            $sample = $samples->get($row->sample_item_id);

            return [
                'product' => $sample?->product,
                'name' => $sample?->localizedName() ?? 'Produit supprimé',
                'quantity' => (int) $row->quantity,
                'revenue_cents' => (int) $row->revenue_cents,
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

        // La commission n'est pas un détail du canal, c'est son prix : un
        // canal qui vend plus et rend plus n'est pas le meilleur, et seule
        // la colonne nette le dit.
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
     * Le tuyau des commandes en cours. Chaque étape porte la couleur que la
     * liste des commandes donne déjà à ce statut : c'est la même distinction
     * que l'œil y a apprise, et la rappeler ici évite d'en enseigner une
     * seconde pour la même chose.
     *
     * @return Collection<int, array{status: string, label: string, count: int, open: bool}>
     */
    public function pipeline(): Collection
    {
        // Remboursée n'est pas une étape de plus, c'est la sortie : elle
        // ferme la ligne au lieu de l'avancer. Elle est comptée ici quand
        // même, parce qu'une commande sortie du tuyau reste une commande
        // dont le tuyau doit rendre compte.
        $statuses = ['placed', 'preparing', 'shipped', 'in_transit', 'delivered', 'refunded'];

        $counts = Order::query()
            ->whereNull('archived_at')
            ->excludingTest()
            ->whereIn('status', $statuses)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Ouverte veut dire qu'il reste quelque chose à faire. Livrée et
        // remboursée sont des fins : elles se comptent, mais elles ne
        // tiennent plus de place dans la barre, sans quoi la seule chose
        // qu'elle montrerait, à mesure que la boutique vieillit, serait
        // combien de commandes sont déjà finies.
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

        // Combien de ces références sont déjà commandées. Une rupture dont
        // le réassort est parti n'appelle pas la même chose qu'une rupture
        // que personne n'a encore traitée, et la puce ne disait pas la
        // différence : elle envoyait chercher soixante-trois fiches dont
        // douze n'attendaient plus que le facteur.
        $awaited = fn (Builder $line) => $line
            ->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->whereHas('purchaseOrder', fn (Builder $order) => $order->open());

        // Les lignes du produit ou celles de ses déclinaisons : c'est là que
        // vit le réassort d'un produit décliné, comme pour scopeNotOutOfStock.
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
            ->with('user')
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
