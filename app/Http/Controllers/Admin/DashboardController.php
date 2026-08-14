<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $now = now();
        $last7Start = $now->copy()->subDays(6)->startOfDay();
        $last30Start = $now->copy()->subDays(29)->startOfDay();

        $nonRefunded = fn () => Order::query()->whereNotIn('status', ['refunded', 'draft']);

        $orderCount = Order::query()->where('status', '!=', 'draft')->count();
        $draftCount = Order::query()->where('status', 'draft')->count();
        $nonRefundedCount = $nonRefunded()->count();
        $netRevenueCents = (int) $nonRefunded()->sum('total_cents');
        $refundedCents = (int) Order::query()->where('status', 'refunded')->sum('total_cents');
        $averageOrderCents = $nonRefundedCount > 0 ? (int) round($netRevenueCents / $nonRefundedCount) : 0;

        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $dailyRows = Order::query()
            ->where('created_at', '>=', $last7Start)
            ->where('status', '!=', 'draft')
            ->selectRaw("strftime('%Y-%m-%d', created_at) as day, count(*) as orders, sum(case when status != 'refunded' then total_cents else 0 end) as revenue_cents")
            ->groupBy('day')
            ->pluck('revenue_cents', 'day')
            ->all();
        $dailyOrderRows = Order::query()
            ->where('created_at', '>=', $last7Start)
            ->where('status', '!=', 'draft')
            ->selectRaw("strftime('%Y-%m-%d', created_at) as day, count(*) as orders")
            ->groupBy('day')
            ->pluck('orders', 'day')
            ->all();

        $last7Days = collect(range(6, 0))->map(function (int $offset) use ($now, $dailyRows, $dailyOrderRows): array {
            $date = $now->copy()->subDays($offset);
            $key = $date->format('Y-m-d');

            return [
                'date' => $date,
                'orders' => (int) ($dailyOrderRows[$key] ?? 0),
                'revenue_cents' => (int) ($dailyRows[$key] ?? 0),
            ];
        });

        $marketplaceStats = $nonRefunded()
            ->selectRaw("COALESCE(NULLIF(marketplace_name, ''), 'Site (vente directe)') as label, count(*) as orders, sum(total_cents) as revenue_cents")
            ->groupBy('label')
            ->orderByDesc('revenue_cents')
            ->get();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['refunded', 'draft'])
            ->select('order_items.*')
            ->get()
            ->groupBy('product_slug')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'name' => $first->localizedName(),
                    'product' => $first->product,
                    'quantity' => $items->sum('quantity'),
                    'revenue_cents' => $items->sum('line_cents'),
                ];
            })
            ->sortByDesc('quantity')
            ->take(5)
            ->values();

        $lowStockProducts = Product::query()
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 2)
            ->orderBy('quantity')
            ->limit(8)
            ->get();

        $recentOrders = Order::query()->where('status', '!=', 'draft')->with('user')->latest()->limit(8)->get();

        return view('admin.dashboard', [
            'customerCount' => User::query()->where('is_admin', false)->where('external', false)->count(),
            'externalCustomerCount' => User::query()->where('external', true)->count(),
            'newCustomers30d' => User::query()->where('is_admin', false)->where('external', false)->where('created_at', '>=', $last30Start)->count(),
            'productCount' => Product::query()->count(),
            'activeProductCount' => Product::query()->active()->count(),
            'orderCount' => $orderCount,
            'draftCount' => $draftCount,
            'nonRefundedCount' => $nonRefundedCount,
            'netRevenueCents' => $netRevenueCents,
            'refundedCents' => $refundedCents,
            'averageOrderCents' => $averageOrderCents,
            'toPrepareCount' => Order::query()->whereIn('status', ['placed', 'preparing'])->count(),
            'missingTrackingCount' => Order::query()
                ->where('status', 'shipped')
                ->where(function ($query): void {
                    $query->whereNull('tracking_number')->orWhere('tracking_number', '');
                })
                ->count(),
            'lowStockCount' => Product::query()->where('quantity', '>', 0)->where('quantity', '<=', 2)->count(),
            'outOfStockCount' => Product::query()->where('quantity', '<=', 0)->count(),
            'statusCounts' => $statusCounts,
            'last7Days' => $last7Days,
            'marketplaceStats' => $marketplaceStats,
            'topProducts' => $topProducts,
            'lowStockProducts' => $lowStockProducts,
            'recentOrders' => $recentOrders,
        ]);
    }
}
