<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetrics;
use App\Services\DashboardPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    private const PERIOD_COOKIE = 'admin_dashboard_period';

    public function __invoke(Request $request): Response
    {
        // Un ?period= explicite l'emporte toujours ; sinon on reprend celui
        // de la dernière visite, comme la liste des produits pour son tri.
        $key = array_key_exists((string) $request->query('period'), DashboardPeriod::OPTIONS)
            ? (string) $request->query('period')
            : (string) $request->cookie(self::PERIOD_COOKIE, DashboardPeriod::DEFAULT);

        $period = DashboardPeriod::resolve($key);
        $metrics = new DashboardMetrics($period);

        return response()->view('admin.dashboard', [
            'period' => $period,
            'headline' => $metrics->headline(),
            'sparklines' => $metrics->sparklines(),
            'revenueSeries' => $metrics->revenueSeries(),
            'attention' => $metrics->attention(),
            'topProducts' => $metrics->topProducts(),
            'channelSplit' => $metrics->channelSplit(),
            'pipeline' => $metrics->pipeline(),
            'bestCustomers' => $metrics->bestCustomers(),
            'stockMovements' => $metrics->recentStockMovements(),
            'stockAlertProducts' => $metrics->stockAlertProducts(),
            'recentOrders' => $metrics->recentOrders(),
            'reference' => $metrics->reference(),
        ])->cookie(self::PERIOD_COOKIE, $period->key, 60 * 24 * 365);
    }
}
