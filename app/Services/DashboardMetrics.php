<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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
    public function __construct(private readonly DashboardPeriod $period) {}

    /** Commandes qui comptent comme du chiffre d'affaires. */
    private function salesQuery(): Builder
    {
        return Order::query()->excludingTest()->whereNotIn('status', ['refunded', 'draft']);
    }

    private function between(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
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
                User::query()->where('is_admin', false)->where('external', false), $start, $end
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
     * @return Collection<int, array{date: Carbon, label: string, revenue_cents: int, orders: int}>
     */
    private function dailyBuckets(Carbon $start, Carbon $end): Collection
    {
        $rows = $this->between($this->salesQuery(), $start, $end)
            ->get(['created_at', 'total_cents'])
            ->groupBy(fn (Order $order): string => $order->created_at->format('Y-m-d'));

        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());

        return collect(range(0, $days))->map(function (int $offset) use ($start, $rows): array {
            $date = $start->copy()->addDays($offset);
            $bucket = $rows->get($date->format('Y-m-d'));

            return [
                'date' => $date,
                'label' => $date->format('d/m'),
                'revenue_cents' => (int) ($bucket?->sum('total_cents') ?? 0),
                'orders' => (int) ($bucket?->count() ?? 0),
            ];
        });
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
            ->selectRaw("coalesce(nullif(marketplace_name, ''), 'Direct sale') as label, count(*) as orders, sum(total_cents) as revenue_cents")
            ->groupBy('label')
            ->orderByDesc('revenue_cents')
            ->get();

        $head = $rows->take($limit)->map(fn ($row): array => [
            'label' => $row->label,
            'orders' => (int) $row->orders,
            'revenue_cents' => (int) $row->revenue_cents,
        ]);

        $tail = $rows->skip($limit);

        if ($tail->isNotEmpty()) {
            $head->push([
                'label' => 'Other',
                'orders' => (int) $tail->sum('orders'),
                'revenue_cents' => (int) $tail->sum('revenue_cents'),
            ]);
        }

        return $head->values();
    }

    /**
     * Le tuyau des commandes en cours : des étapes ordonnées, pas des
     * catégories — d'où la rampe d'une seule teinte côté affichage.
     *
     * @return Collection<int, array{status: string, label: string, count: int}>
     */
    public function pipeline(): Collection
    {
        $counts = Order::query()
            ->whereNull('archived_at')
            ->excludingTest()
            ->whereIn('status', ['placed', 'preparing', 'shipped', 'in_transit', 'delivered'])
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect([
            'placed' => 'Placed',
            'preparing' => 'Preparing',
            'shipped' => 'Shipped',
            'in_transit' => 'In transit',
            'delivered' => 'Delivered',
        ])->map(fn (string $label, string $status): array => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($counts[$status] ?? 0),
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
        $outOfStock = Product::query()->active()->where('quantity', '<=', 0)->count();
        $lowStock = Product::query()->active()->where('quantity', '>', 0)->where('quantity', '<=', 2)->count();

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
                'level' => 'critical',
                'url' => route('admin.products.index', ['tab' => 'out-of-stock', 'sort' => 'stock-asc']),
            ],
            [
                'key' => 'low-stock',
                'count' => $lowStock,
                'label' => 'low on stock',
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
            ->where('quantity', '<=', 2)
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
            'customers' => User::query()->where('is_admin', false)->where('external', false)->count(),
            'products' => Product::query()->count(),
            'active_products' => Product::query()->active()->count(),
            'drafts' => Order::query()->whereNull('archived_at')->excludingTest()->where('status', 'draft')->count(),
            'external_orders' => Order::query()->whereNull('archived_at')->excludingTest()->where('is_manual', true)->count(),
        ];
    }
}
