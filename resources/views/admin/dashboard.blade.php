@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @php
        $series = $revenueSeries['current'];
        $previousSeries = $revenueSeries['previous'];
        $channelTotal = max(1, $channelSplit->sum('revenue_cents'));
        $pipelineTotal = max(1, $pipeline->sum('count'));
        $topQuantityMax = max(1, $topProducts->max('quantity') ?? 1);
    @endphp

    <div class="admin-list-page admin-dashboard">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Overview</p>
                    <h2 class="admin-list-title">Dashboard</h2>
                    <p class="admin-list-lede">Sales, stock and customers over {{ strtolower($period->label()) }}.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
                </div>
            </div>

            {{-- Un seul sélecteur, au-dessus de tout ce qu'il cadre : chaque
                 chiffre et chaque courbe de la page suivent la même tranche.
                 En GET pour que la vue reste partageable et que le bouton
                 retour fonctionne. --}}
            <nav class="dash-periods" aria-label="Reporting period">
                @foreach (\App\Services\DashboardPeriod::OPTIONS as $key => $label)
                    <a
                        href="{{ route('admin.dashboard', ['period' => $key]) }}"
                        class="dash-period {{ $period->key === $key ? 'is-active' : '' }}"
                        @if ($period->key === $key) aria-current="page" @endif
                    >{{ $label }}</a>
                @endforeach
            </nav>
        </header>

        {{-- Rien ici quand tout est en ordre : une bande d'alerte toujours
             pleine apprend à l'ignorer. --}}
        @if ($attention->isNotEmpty())
            <section class="dash-attention" aria-labelledby="dash-attention-title">
                <h3 class="dash-attention-title" id="dash-attention-title">Needs attention</h3>
                <ul class="dash-attention-list">
                    @foreach ($attention as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="dash-chip is-{{ $item['level'] }}">
                                <svg class="dash-chip-icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
                                    @if ($item['level'] === 'critical')
                                        <circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                        <path d="M8 4.6v4.2M8 11.2v.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    @elseif ($item['level'] === 'serious')
                                        <path d="M8 2.2 14.4 13H1.6L8 2.2Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                        <path d="M8 6.6v2.6M8 11.2v.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    @else
                                        <circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                        <path d="M8 4.8v3.6l2.4 1.4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    @endif
                                </svg>
                                <span class="dash-chip-count">{{ number_format($item['count']) }}</span>
                                <span class="dash-chip-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="dash-performance" aria-labelledby="dash-performance-title">
            <h3 class="sr-only" id="dash-performance-title">Performance</h3>

            <div class="dash-hero-row">
                <div class="dash-hero">
                    <span class="dash-hero-label">Revenue</span>
                    <span class="dash-hero-value">{{ format_euros($headline['revenue_cents']) }}</span>
                    <span class="dash-hero-foot">
                        @php
                            $heroDelta = $headline['revenue_delta'];
                            $heroTone = $heroDelta['direction'] === 'flat'
                                ? 'flat'
                                : ($heroDelta['direction'] === 'up' ? 'good' : 'bad');
                        @endphp
                        @if ($heroDelta['percent'] === null)
                            <span class="dash-delta is-flat">—</span>
                        @else
                            <span class="dash-delta is-{{ $heroTone }}">
                                <span aria-hidden="true">{{ $heroDelta['direction'] === 'up' ? '▲' : '▼' }}</span>{{ $heroDelta['percent'] > 0 ? '+' : '' }}{{ number_format($heroDelta['percent'], 1) }}%
                            </span>
                        @endif
                        <span class="dash-tile-compare">{{ $period->comparisonLabel() }}</span>
                    </span>
                </div>

                <div class="dash-tiles">
                    @include('admin.partials.stat-tile', [
                        'label' => 'Orders',
                        'value' => number_format($headline['orders']),
                        'delta' => $headline['orders_delta'],
                        'upIsGood' => true,
                        'comparison' => $period->comparisonLabel(),
                        'points' => $sparklines['orders'],
                    ])
                    @include('admin.partials.stat-tile', [
                        'label' => 'Average order',
                        'value' => format_euros($headline['average_order_cents']),
                        'delta' => $headline['average_order_delta'],
                        'upIsGood' => true,
                        'comparison' => $period->comparisonLabel(),
                        'points' => $sparklines['revenue'],
                    ])
                    @include('admin.partials.stat-tile', [
                        'label' => 'Refunded',
                        'value' => format_euros($headline['refunded_cents']),
                        'delta' => $headline['refunded_delta'],
                        'upIsGood' => false,
                        'comparison' => $period->comparisonLabel(),
                    ])
                    @include('admin.partials.stat-tile', [
                        'label' => 'New customers',
                        'value' => number_format($headline['new_customers']),
                        'delta' => $headline['new_customers_delta'],
                        'upIsGood' => true,
                        'comparison' => $period->comparisonLabel(),
                    ])
                </div>
            </div>

            {{-- Chaque graphique a son jumeau en tableau, rendu côté serveur :
                 sans JavaScript le canvas reste vide, et une infobulle ne doit
                 jamais être le seul moyen de lire une valeur. --}}
            <section class="order-panel dash-chart-panel">
                <div class="dash-panel-head">
                    <h3 class="order-panel-title">Revenue over time</h3>
                    <span class="dash-legend">
                        <span class="dash-legend-item"><span class="dash-swatch dash-swatch--current"></span>{{ $period->label() }}</span>
                        <span class="dash-legend-item"><span class="dash-swatch dash-swatch--previous"></span>Previous period</span>
                    </span>
                </div>

                <div
                    class="dash-chart"
                    data-revenue-chart
                    data-current="{{ json_encode($series->map(fn ($d) => $d['revenue_cents'] / 100)->all()) }}"
                    data-previous="{{ json_encode($previousSeries->map(fn ($d) => $d['revenue_cents'] / 100)->all()) }}"
                    data-labels="{{ json_encode($series->pluck('label')->all()) }}"
                >
                    <canvas height="220" aria-hidden="true"></canvas>
                </div>

                <details class="dash-table-view">
                    <summary>Table view</summary>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <caption class="sr-only">Revenue per day, current and previous period</caption>
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th class="admin-table-num">Orders</th>
                                    <th class="admin-table-num">Revenue</th>
                                    <th class="admin-table-num">Previous</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($series as $index => $day)
                                    <tr>
                                        <td>{{ $day['label'] }}</td>
                                        <td class="admin-table-num">{{ number_format($day['orders']) }}</td>
                                        <td class="admin-table-num">{{ format_euros($day['revenue_cents']) }}</td>
                                        <td class="admin-table-num">{{ format_euros($previousSeries[$index]['revenue_cents'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>

            <div class="dash-grid">
                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Top products</h3>
                        <span class="dash-panel-note">{{ strtolower($period->label()) }}</span>
                    </div>

                    @if ($topProducts->isEmpty())
                        <p class="empty-state">Nothing sold in this period.</p>
                    @else
                        <div class="admin-table-wrap">
                            <table class="admin-table dash-bar-table">
                                <thead>
                                    <tr>
                                        <th class="admin-table-media"></th>
                                        <th>Product</th>
                                        <th class="admin-table-num">Units</th>
                                        <th class="admin-table-num">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($topProducts as $row)
                                        <tr>
                                            {{-- La vignette vient de la fiche produit, pas de
                                                 l'image figée dans la ligne : le tableau de bord
                                                 suit le produit tel qu'il est aujourd'hui. --}}
                                            <td class="admin-table-media">
                                                @if ($row['product'] && filled($row['product']->image))
                                                    <a href="{{ route('admin.products.edit', $row['product']) }}" class="admin-stock-media">
                                                        <img src="{{ $row['product']->imageUrl() }}" alt="" width="44" height="44" loading="lazy">
                                                    </a>
                                                @else
                                                    {{-- Produit supprimé ou sans visuel : la tuile garde
                                                         sa place pour que la colonne reste alignée. --}}
                                                    <span class="admin-stock-media is-empty" aria-hidden="true"></span>
                                                @endif
                                            </td>
                                            <td>
                                                {{-- Une seule couleur pour toutes les barres : des
                                                     catégories nominales, donc rien à encoder dans la
                                                     teinte que la longueur ne dise déjà. --}}
                                                <span class="dash-bar-cell">
                                                    <span class="dash-bar" style="--bar-width: {{ round($row['quantity'] / $topQuantityMax * 100, 2) }}%"></span>
                                                    @if ($row['product'])
                                                        <a href="{{ route('admin.products.edit', $row['product']) }}" class="admin-table-strong admin-table-truncate" title="{{ $row['name'] }}">{{ $row['name'] }}</a>
                                                    @else
                                                        <span class="admin-table-strong admin-table-truncate">{{ $row['name'] }}</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="admin-table-num">{{ number_format($row['quantity']) }}</td>
                                            <td class="admin-table-num">{{ format_euros($row['revenue_cents']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Channel split</h3>

                    @if ($channelSplit->isEmpty())
                        <p class="empty-state">No sales in this period.</p>
                    @else
                        {{-- Barre empilée horizontale : part d'un tout, et des
                             noms de canaux trop longs pour un axe vertical.
                             Les segments sont séparés par un filet de la
                             couleur du fond, jamais par une bordure. --}}
                        <div class="dash-stack" role="img" aria-label="Revenue share by channel">
                            @foreach ($channelSplit as $index => $channel)
                                <span
                                    class="dash-stack-segment dash-series-{{ min($index + 1, 4) }}"
                                    style="--segment-width: {{ round($channel['revenue_cents'] / $channelTotal * 100, 2) }}%"
                                ></span>
                            @endforeach
                        </div>

                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Channel</th>
                                        <th class="admin-table-num">Orders</th>
                                        <th class="admin-table-num">Revenue</th>
                                        <th class="admin-table-num">Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($channelSplit as $index => $channel)
                                        <tr>
                                            <td>
                                                <span class="dash-swatch dash-series-{{ min($index + 1, 4) }}"></span>
                                                {{ $channel['label'] }}
                                            </td>
                                            <td class="admin-table-num">{{ number_format($channel['orders']) }}</td>
                                            <td class="admin-table-num">{{ format_euros($channel['revenue_cents']) }}</td>
                                            <td class="admin-table-num">{{ number_format($channel['revenue_cents'] / $channelTotal * 100, 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Order pipeline</h3>

                    {{-- Des étapes ordonnées, pas des catégories : une seule
                         teinte du clair au foncé, jamais la palette
                         catégorielle. --}}
                    <div class="dash-stack" role="img" aria-label="Open orders by stage">
                        @foreach ($pipeline as $index => $stage)
                            @if ($stage['count'] > 0)
                                <span
                                    class="dash-stack-segment dash-stage-{{ $index + 1 }}"
                                    style="--segment-width: {{ round($stage['count'] / $pipelineTotal * 100, 2) }}%"
                                ></span>
                            @endif
                        @endforeach
                    </div>

                    <ul class="dash-pipeline-list">
                        @foreach ($pipeline as $index => $stage)
                            <li>
                                <span class="dash-swatch dash-stage-{{ $index + 1 }}"></span>
                                <span class="dash-pipeline-label">{{ $stage['label'] }}</span>
                                <span class="dash-pipeline-count">{{ number_format($stage['count']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Recent orders</h3>

                    @if ($recentOrders->isEmpty())
                        <p class="empty-state">No orders yet.</p>
                    @else
                        <ul class="dash-list">
                            @foreach ($recentOrders as $order)
                                <li>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">{{ $order->number }}</a>
                                    <span class="admin-table-sub">{{ $order->user?->name ?? 'Guest' }} · {{ $order->created_at->format('d/m') }}</span>
                                    <span class="dash-list-value">{{ format_euros($order->total_cents) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Best customers</h3>
                        <span class="dash-panel-note">{{ strtolower($period->label()) }}</span>
                    </div>

                    @if ($bestCustomers->isEmpty())
                        <p class="empty-state">No customer orders in this period.</p>
                    @else
                        <ul class="dash-list">
                            @foreach ($bestCustomers as $row)
                                <li>
                                    @if ($row['user'])
                                        <a href="{{ route('admin.customers.show', $row['user']) }}" class="admin-table-strong">{{ $row['name'] }}</a>
                                    @else
                                        <span class="admin-table-strong">{{ $row['name'] }}</span>
                                    @endif
                                    <span class="admin-table-sub">{{ number_format($row['orders']) }} {{ \Illuminate\Support\Str::plural('order', $row['orders']) }}</span>
                                    <span class="dash-list-value">{{ format_euros($row['revenue_cents']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @if ($stockAlertProducts->isNotEmpty())
                    {{-- Seul panneau à porter un formulaire : il prend toute la
                         largeur de la grille, sinon quantité, raison et bouton
                         se tassent dans une colonne prévue pour des listes. --}}
                    <section class="order-panel dash-panel--wide">
                        <div class="dash-panel-head">
                            <h3 class="order-panel-title">Stock alerts</h3>
                            <a href="{{ route('admin.products.index', ['tab' => 'out-of-stock', 'sort' => 'stock-asc']) }}" class="dash-panel-note">View products</a>
                        </div>

                        {{-- La puce d'alerte signale ; ici on agit. Le champ
                             raison reste facultatif, mais c'est lui qui rend le
                             journal de stock relisible plus tard. --}}
                        <ul class="dash-stock-list">
                            @foreach ($stockAlertProducts as $product)
                                @php($isOut = $product->quantity <= 0)
                                <li>
                                    @if (filled($product->image))
                                        <a href="{{ route('admin.products.edit', $product) }}" class="admin-stock-media">
                                            <img src="{{ $product->imageUrl() }}" alt="" width="44" height="44" loading="lazy">
                                        </a>
                                    @else
                                        <span class="admin-stock-media is-empty" aria-hidden="true"></span>
                                    @endif
                                    <div class="admin-dash-list-main">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="admin-table-strong admin-table-truncate" title="{{ $product->localizedName() }}">{{ $product->localizedName() }}</a>
                                        <span class="admin-stock-chip {{ $isOut ? 'is-out' : 'is-low' }}">
                                            {{ $isOut ? 'Out of stock' : $product->quantity.' left' }}
                                        </span>
                                    </div>
                                    <form method="POST" action="{{ route('admin.products.quantity', $product) }}" class="admin-restock-form">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="dash-stock-qty-{{ $product->id }}">Quantity for {{ $product->localizedName() }}</label>
                                        <input id="dash-stock-qty-{{ $product->id }}" type="number" name="quantity" value="{{ $product->quantity }}" min="0" class="admin-restock-input">
                                        <label class="sr-only" for="dash-stock-note-{{ $product->id }}">Reason for {{ $product->localizedName() }}</label>
                                        <input id="dash-stock-note-{{ $product->id }}" type="text" name="note" class="admin-restock-note" maxlength="255" placeholder="Reason (optional)">
                                        <button type="submit" class="btn btn-sm btn-secondary">Save</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Recent stock movements</h3>
                        <span class="dash-panel-note">{{ strtolower($period->label()) }}</span>
                    </div>

                    @if ($stockMovements->isEmpty())
                        <p class="empty-state">No stock moved in this period.</p>
                    @else
                        <ul class="dash-list">
                            @foreach ($stockMovements as $movement)
                                <li>
                                    @if ($movement->product)
                                        <a href="{{ route('admin.products.stock-history', $movement->product) }}" class="admin-table-strong admin-table-truncate" title="{{ $movement->product->localizedName() }}">{{ $movement->product->localizedName() }}</a>
                                    @else
                                        <span class="admin-table-strong">Deleted product</span>
                                    @endif
                                    <span class="admin-table-sub">{{ $movement->reason->label() }} · {{ $movement->created_at->format('d/m') }}</span>
                                    <span class="dash-list-value dash-delta is-{{ $movement->delta >= 0 ? 'good' : 'bad' }}">
                                        {{ $movement->delta >= 0 ? '+' : '−' }}{{ number_format(abs($movement->delta)) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- Des chiffres qu'on consulte, pas des signaux : une ligne
                 discrète plutôt que des cartes de la même taille que le reste. --}}
            <p class="dash-reference">
                <a href="{{ route('admin.customers.index') }}">{{ number_format($reference['customers']) }} customers</a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('admin.products.index') }}">{{ number_format($reference['active_products']) }} of {{ number_format($reference['products']) }} products active</a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('admin.orders.index', ['tab' => 'draft']) }}">{{ number_format($reference['drafts']) }} draft {{ \Illuminate\Support\Str::plural('order', $reference['drafts']) }}</a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('admin.orders.index') }}">{{ number_format($reference['external_orders']) }} manual {{ \Illuminate\Support\Str::plural('order', $reference['external_orders']) }}</a>
            </p>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/vendor/chart.umd.min.js') }}" defer></script>
    <script src="{{ versioned_asset('js/admin-dashboard-charts.js') }}" defer></script>
@endpush
