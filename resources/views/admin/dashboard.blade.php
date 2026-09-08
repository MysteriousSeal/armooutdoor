@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @php
        $series = $revenueSeries['current'];
        $previousSeries = $revenueSeries['previous'];
        $channelTotal = max(1, $channelSplit->sum('revenue_cents'));
        // The bar scales open work only: finished orders would crush
        // everything else as the shop ages.
        $pipelineOpen = $pipeline->where('open', true);
        $pipelineClosed = $pipeline->where('open', false);
        $pipelineTotal = max(1, $pipelineOpen->sum('count'));
        $topQuantityMax = max(1, $topProducts->max('quantity') ?? 1);

        // The bar is a share of the revenue of the priced orders only, like
        // the margin: against the total, its three parts would cover sales
        // the goods cost never reached, and would add up to nothing.
        $ledgerBase = max(1, $money['priced_revenue_cents']);
        $ledgerShare = fn (int $cents): float => round(min(100, max(0, $cents / $ledgerBase * 100)), 2);
        $ledgerIsPartial = $money['priced_orders'] < $money['total_orders'];

        // Past four months the series counts by month, and everything that
        // names it follows: "Orders per day" over a monthly column would
        // announce a figure it does not carry.
        $bucket = $period->bucketsByMonth() ? 'month' : 'day';
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
                                @if (($item['note'] ?? null) !== null)
                                    {{-- What has already gone to the
                                         supplier: the chip says how many of
                                         these references are waiting on
                                         nothing but the postman. --}}
                                    <span class="dash-chip-note">{{ $item['note'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="dash-performance" aria-labelledby="dash-performance-title">
            <h3 class="sr-only" id="dash-performance-title">Performance</h3>

            {{-- The ledger line. Four terms and two operators: what came in,
                 what went out of it, what the goods cost, what is left.
                 Written as the subtraction it is, and because revenue alone
                 does not say whether the shop makes money. --}}
            <section class="dash-ledger" aria-labelledby="dash-ledger-title">
                <div class="dash-ledger-head">
                    <h3 class="order-panel-title" id="dash-ledger-title">What the shop kept</h3>
                    <span class="dash-panel-note">{{ strtolower($period->label()) }} · {{ $period->comparisonLabel() }}</span>
                </div>

                <div class="dash-ledger-line">
                    <div class="dash-term dash-term--revenue">
                        <span class="dash-term-label">Revenue</span>
                        <span class="dash-term-value">{{ format_euros($money['revenue_cents']) }}</span>
                        <span class="dash-term-note">
                            @include('admin.partials.delta', ['delta' => $headline['revenue_delta'], 'upIsGood' => true])
                            {{ number_format($money['total_orders']) }} {{ \Illuminate\Support\Str::plural('order', $money['total_orders']) }}
                        </span>
                    </div>

                    <span class="dash-operator" aria-hidden="true">−</span>

                    <div class="dash-term dash-term--cost">
                        <span class="dash-term-label">Selling costs</span>
                        <span class="dash-term-value">{{ format_euros($money['order_costs_cents']) }}</span>
                        <span class="dash-term-note">
                            {{ format_euros($money['shipping_cents']) }} shipping ·
                            {{ format_euros($money['commission_cents']) }} commission ·
                            {{ format_euros($money['fee_cents']) }} fees
                        </span>
                    </div>

                    <span class="dash-operator" aria-hidden="true">−</span>

                    <div class="dash-term dash-term--goods">
                        <span class="dash-term-label">Goods</span>
                        <span class="dash-term-value">{{ format_euros($money['product_cost_cents']) }}</span>
                        <span class="dash-term-note">
                            {{-- An order with a line that has no purchase
                                 history leaves the calculation rather than
                                 entering it at zero: the counter says how
                                 many that affects. --}}
                            at average purchase cost, on {{ number_format($money['priced_orders']) }} of {{ number_format($money['total_orders']) }} {{ \Illuminate\Support\Str::plural('order', $money['total_orders']) }}
                        </span>
                    </div>

                    <span class="dash-operator dash-operator--equals" aria-hidden="true">=</span>

                    <div class="dash-term dash-term--profit">
                        <span class="dash-term-label">Profit</span>
                        <span class="dash-term-value">{{ format_euros($money['profit_cents']) }}</span>
                        <span class="dash-term-note">
                            @include('admin.partials.delta', ['delta' => $money['profit_delta'], 'upIsGood' => true])
                            @if ($money['margin_percent'] !== null)
                                {{ number_format($money['margin_percent'], 1) }}% margin
                            @endif
                        </span>
                    </div>
                </div>

                {{-- The same subtraction, to scale: each segment is its share
                     of revenue, and the white to the right is what could not
                     be priced. --}}
                <div class="dash-ledger-bar" role="img" aria-label="Share of revenue taken by costs, goods and profit">
                    <span class="dash-ledger-segment is-cost" style="--segment-width: {{ $ledgerShare($money['priced_costs_cents']) }}%"></span>
                    <span class="dash-ledger-segment is-goods" style="--segment-width: {{ $ledgerShare($money['product_cost_cents']) }}%"></span>
                    <span class="dash-ledger-segment is-profit" style="--segment-width: {{ $ledgerShare(max(0, $money['profit_cents'])) }}%"></span>
                </div>

                <ul class="dash-ledger-key">
                    <li><span class="dash-swatch is-cost"></span>Selling costs {{ number_format($ledgerShare($money['priced_costs_cents']), 1) }}%</li>
                    <li><span class="dash-swatch is-goods"></span>Goods {{ number_format($ledgerShare($money['product_cost_cents']), 1) }}%</li>
                    <li><span class="dash-swatch is-profit"></span>Profit {{ number_format($ledgerShare(max(0, $money['profit_cents'])), 1) }}%</li>
                    @if ($ledgerIsPartial)
                        <li>shares of the {{ format_euros($money['priced_revenue_cents']) }} that could be priced</li>
                    @endif
                    @if ($money['markup_percent'] !== null)
                        <li class="dash-ledger-key-note">{{ number_format($money['markup_percent'], 1) }}% return on every euro of stock sold</li>
                    @endif
                </ul>
            </section>

            <div class="dash-tiles dash-tiles--five">
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
                    'label' => 'New customers',
                    'value' => number_format($headline['new_customers']),
                    'delta' => $headline['new_customers_delta'],
                    'upIsGood' => true,
                    'comparison' => $period->comparisonLabel(),
                ])
                <div class="dash-tile">
                    <span class="dash-tile-label">Returning buyers</span>
                    <span class="dash-tile-value">{{ $customers['returning_percent'] === null ? '—' : number_format($customers['returning_percent'], 1).'%' }}</span>
                    <span class="dash-tile-foot">
                        {{ number_format($customers['returning']) }} of {{ number_format($customers['buyers']) }} who bought had bought before
                    </span>
                </div>
                @include('admin.partials.stat-tile', [
                    'label' => 'Refunded',
                    'value' => format_euros($headline['refunded_cents']),
                    'delta' => $headline['refunded_delta'],
                    'upIsGood' => false,
                    'comparison' => $period->comparisonLabel(),
                ])
            </div>

            {{-- Chaque graphique a son jumeau en tableau, rendu côté serveur :
                 sans JavaScript le canvas reste vide, et une infobulle ne doit
                 jamais être le seul moyen de lire une valeur. --}}
            <div class="dash-row dash-row--chart">
                <section class="order-panel dash-chart-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Revenue over time</h3>
                        <span class="dash-legend">
                            <span class="dash-legend-item"><span class="dash-swatch dash-swatch--current"></span>{{ $period->label() }}</span>
                            @if ($previousSeries->isNotEmpty())
                                <span class="dash-legend-item"><span class="dash-swatch dash-swatch--previous"></span>Previous period</span>
                            @endif
                        </span>
                    </div>

                    <div
                        class="dash-chart"
                        data-revenue-chart
                        data-bucket="{{ $bucket }}"
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
                                <caption class="sr-only">Revenue per {{ $bucket }}, current and previous period</caption>
                                <thead>
                                    <tr>
                                        <th>{{ ucfirst($bucket) }}</th>
                                        <th class="admin-table-num">Orders</th>
                                        <th class="admin-table-num">Revenue</th>
                                        @if ($previousSeries->isNotEmpty())
                                            <th class="admin-table-num">Previous</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($series as $index => $day)
                                        <tr>
                                            <td>{{ $day['label'] }}</td>
                                            <td class="admin-table-num">{{ number_format($day['orders']) }}</td>
                                            <td class="admin-table-num">{{ format_euros($day['revenue_cents']) }}</td>
                                            @if ($previousSeries->isNotEmpty())
                                                <td class="admin-table-num">{{ format_euros($previousSeries[$index]['revenue_cents'] ?? 0) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </section>

                {{-- The count of orders per day, in bars: a quantity counted
                     per interval, not a value that flows.

                     Its table twin is the neighbouring panel's, whose Orders
                     column carries the same values day by day: two identical
                     tables side by side would leave nobody better off. --}}
                <section class="order-panel dash-chart-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Orders per {{ $bucket }}</h3>
                        <span class="dash-panel-note">{{ number_format($headline['orders']) }} in {{ strtolower($period->label()) }}</span>
                    </div>

                    <div
                        class="dash-chart dash-chart--short"
                        data-orders-chart
                        data-current="{{ json_encode($series->pluck('orders')->all()) }}"
                        data-labels="{{ json_encode($series->pluck('label')->all()) }}"
                    >
                        <canvas height="160" aria-hidden="true"></canvas>
                    </div>

                    <p class="dash-panel-foot">
                        Busiest {{ $bucket }} {{ $series->sortByDesc('orders')->first()['label'] ?? '—' }}
                        · {{ number_format($series->max('orders') ?? 0) }} {{ \Illuminate\Support\Str::plural('order', $series->max('orders') ?? 0) }}
                        · {{ number_format($series->where('orders', 0)->count()) }} quiet {{ \Illuminate\Support\Str::plural($bucket, $series->where('orders', 0)->count()) }}
                    </p>
                </section>
            </div>

            <div class="dash-row dash-row--wide-narrow">
                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Top products</h3>
                        <span class="dash-panel-note">{{ strtolower($period->label()) }}</span>
                    </div>

                    @if ($topProducts->isEmpty())
                        <p class="empty-state">Nothing sold in this period.</p>
                    @else
                        {{-- No sideways scrolling inside a panel: it is the
                             name that gives, not the column. --}}
                        <div class="admin-table-wrap admin-table-wrap--tight">
                            <table class="admin-table dash-bar-table">
                                <thead>
                                    <tr>
                                        <th class="dash-rank-head"><span class="sr-only">Rank</span></th>
                                        <th class="admin-table-media"></th>
                                        <th>Product</th>
                                        <th class="admin-table-num">Units</th>
                                        <th class="admin-table-num">Avg sold at</th>
                                        <th class="admin-table-num">Avg cost</th>
                                        <th class="admin-table-num">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($topProducts as $index => $row)
                                        <tr>
                                            {{-- A numbered rank: the panel is a
                                                 ranking, and that is the one
                                                 thing the row order says
                                                 without showing it. --}}
                                            <td class="dash-rank">{{ $index + 1 }}</td>
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
                                                    @if ($row['product'])
                                                        <a href="{{ route('admin.products.edit', $row['product']) }}" class="admin-table-strong admin-table-truncate" title="{{ $row['name'] }}">{{ $row['name'] }}</a>
                                                    @else
                                                        <span class="admin-table-strong admin-table-truncate">{{ $row['name'] }}</span>
                                                    @endif
                                                    @if (filled($row['sku']))
                                                        {{-- The reference under the name: it is what one
                                                             reads aloud to a supplier, and quicker to
                                                             recognise than a title that truncates. --}}
                                                        <span class="dash-bar-sku">{{ $row['sku'] }}</span>
                                                    @endif
                                                    {{-- The share of sales as a rule under the name rather
                                                         than a wash behind it: text reads better on white,
                                                         and a bar on its own line compares from one row to
                                                         the next. --}}
                                                    <span class="dash-bar" style="--bar-width: {{ round($row['quantity'] / $topQuantityMax * 100, 2) }}%"></span>
                                                </span>
                                            </td>
                                            <td class="admin-table-num">{{ number_format($row['quantity']) }}</td>
                                            <td class="admin-table-num dash-unit-price">
                                                {{-- Averages, not list prices: what
                                                     the unit went for, and what it
                                                     cost to put on the shelf. --}}
                                                {{ $row['unit_price_cents'] === null ? '—' : format_euros($row['unit_price_cents']) }}
                                            </td>
                                            <td class="admin-table-num dash-unit-price">
                                                @if ($row['unit_cost_cents'] === null)
                                                    <span title="No purchase history for this product">—</span>
                                                @else
                                                    {{ format_euros($row['unit_cost_cents']) }}
                                                @endif
                                            </td>
                                            <td class="admin-table-num">{{ format_euros($row['revenue_cents']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Channel split</h3>
                        <span class="dash-panel-note">net of commission</span>
                    </div>

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
                                        <th class="admin-table-num">Commission</th>
                                        <th class="admin-table-num">Net</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($channelSplit as $index => $channel)
                                        <tr>
                                            <td>
                                                <span class="dash-swatch dash-series-{{ min($index + 1, 4) }}"></span>
                                                {{ $channel['label'] }}
                                                <span class="admin-table-sub">{{ number_format($channel['revenue_cents'] / $channelTotal * 100, 1) }}% of revenue</span>
                                            </td>
                                            <td class="admin-table-num">{{ number_format($channel['orders']) }}</td>
                                            <td class="admin-table-num">{{ format_euros($channel['revenue_cents']) }}</td>
                                            <td class="admin-table-num">
                                                @if ($channel['commission_cents'] > 0)
                                                    <span class="dash-cost-figure">− {{ format_euros($channel['commission_cents']) }}</span>
                                                @else
                                                    <span class="admin-table-sub">—</span>
                                                @endif
                                            </td>
                                            <td class="admin-table-num admin-table-strong">{{ format_euros($channel['net_cents']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div class="dash-row dash-row--thirds">
                {{-- The warehouse: what is on the shelves, what it is worth,
                     and what is already paid for to arrive. The four
                     catalogue tiles held the same space without ever saying
                     what any of it cost. --}}
                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Warehouse</h3>
                        <a href="{{ route('admin.purchase-orders.index') }}" class="dash-panel-note">Purchase orders</a>
                    </div>

                    {{-- What the shelves cost and what they would fetch: one
                         says nothing without the other, and it is the gap
                         between them that sleeps on the shelves. --}}
                    <div class="dash-figure dash-figure--pair">
                        <div class="dash-figure-half">
                            <span class="dash-figure-label">Cost</span>
                            <span class="dash-figure-value">{{ format_euros($stockValue['warehouse_cents']) }}</span>
                            <span class="dash-figure-note">{{ number_format($stockValue['valued_units']) }} {{ \Illuminate\Support\Str::plural('unit', $stockValue['valued_units']) }} at average purchase cost</span>
                        </div>
                        <div class="dash-figure-half dash-figure-half--retail">
                            <span class="dash-figure-label">If it all sold</span>
                            <span class="dash-figure-value">{{ format_euros($stockValue['retail_cents']) }}</span>
                            <span class="dash-figure-note">every unit on the shelves, at today's price</span>
                        </div>
                    </div>

                    @if ($stockValue['shelf_markup_percent'] !== null)
                        {{-- The margin covers only the references whose two
                             ends are known: taking a partial cost off a
                             complete price would claim a profit the shelves
                             do not carry. --}}
                        <p class="dash-shelf-margin">
                            <span class="dash-shelf-margin-value">+{{ format_euros($stockValue['shelf_margin_cents']) }}</span>
                            waiting on the shelves, {{ number_format($stockValue['shelf_markup_percent'], 1) }}% on what it cost
                            @if ($stockValue['unpriced_references'] > 0)
                                <span class="dash-shelf-margin-note">on the {{ format_euros($stockValue['retail_of_valued_cents']) }} whose cost is known</span>
                            @endif
                        </p>
                    @endif

                    <ul class="dash-facts">
                        <li>
                            <span class="dash-fact-label">References for sale</span>
                            <span class="dash-fact-value">{{ number_format($catalogue['references']) }}</span>
                            <span class="dash-fact-note">{{ number_format($catalogue['products']) }} {{ \Illuminate\Support\Str::plural('product', $catalogue['products']) }} · {{ number_format($catalogue['variants']) }} {{ \Illuminate\Support\Str::plural('variant', $catalogue['variants']) }}</span>
                        </li>
                        <li>
                            <span class="dash-fact-label">Units in stock</span>
                            <span class="dash-fact-value">{{ number_format($catalogue['stock_units']) }}</span>
                            <span class="dash-fact-note">received and on the shelves</span>
                        </li>
                        <li>
                            <span class="dash-fact-label">Committed to suppliers</span>
                            <span class="dash-fact-value">{{ format_euros($stockValue['committed_cents']) }}</span>
                            <span class="dash-fact-note">{{ number_format($stockValue['open_purchase_orders']) }} open purchase {{ \Illuminate\Support\Str::plural('order', $stockValue['open_purchase_orders']) }}</span>
                        </li>
                        <li>
                            <span class="dash-fact-label">Still to receive</span>
                            <span class="dash-fact-value">{{ number_format($catalogue['stock_incoming']) }}</span>
                            <span class="dash-fact-note">{{ number_format($catalogue['references_incoming']) }} {{ \Illuminate\Support\Str::plural('reference', $catalogue['references_incoming']) }} not yet for sale</span>
                        </li>
                    </ul>

                    @if ($stockValue['unpriced_references'] > 0)
                        {{-- A reference with no purchase history is not
                             counted at zero: it is said separately, or the
                             value would fall as the catalogue grows. --}}
                        <p class="dash-panel-foot">
                            {{ number_format($stockValue['unpriced_references']) }} {{ \Illuminate\Support\Str::plural('reference', $stockValue['unpriced_references']) }} in stock with no purchase history, left out of the value.
                        </p>
                    @endif
                </section>

                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Customers</h3>
                        <a href="{{ route('admin.customers.index') }}" class="dash-panel-note">All customers</a>
                    </div>

                    <div class="dash-figure">
                        <span class="dash-figure-value">{{ format_euros($customers['lifetime_value_cents']) }}</span>
                        <span class="dash-figure-note">average spent per customer, since the shop opened</span>
                    </div>

                    {{-- New and returning over the period: a bar in two
                         parts, loyalty being a proportion before it is a
                         count. --}}
                    @if ($customers['buyers'] > 0)
                        <div class="dash-stack" role="img" aria-label="New and returning buyers this period">
                            <span class="dash-stack-segment dash-series-1" style="--segment-width: {{ round($customers['returning'] / max(1, $customers['buyers']) * 100, 2) }}%"></span>
                            <span class="dash-stack-segment dash-series-2" style="--segment-width: {{ round($customers['new'] / max(1, $customers['buyers']) * 100, 2) }}%"></span>
                        </div>
                        <span class="dash-legend">
                            <span class="dash-legend-item"><span class="dash-swatch dash-series-1"></span>Returning</span>
                            <span class="dash-legend-item"><span class="dash-swatch dash-series-2"></span>New</span>
                        </span>
                    @endif

                    <ul class="dash-facts">
                        <li>
                            <span class="dash-fact-label">Bought this period</span>
                            <span class="dash-fact-value">{{ number_format($customers['buyers']) }}</span>
                            <span class="dash-fact-note">{{ number_format($customers['returning']) }} returning · {{ number_format($customers['new']) }} new</span>
                        </li>
                        <li>
                            <span class="dash-fact-label">Bought more than once</span>
                            <span class="dash-fact-value">{{ $customers['repeat_percent'] === null ? '—' : number_format($customers['repeat_percent'], 1).'%' }}</span>
                            <span class="dash-fact-note">{{ number_format($customers['repeat_buyers']) }} of {{ number_format($customers['lifetime_buyers']) }} customers, all time</span>
                        </li>
                        <li>
                            <span class="dash-fact-label">Orders per customer</span>
                            <span class="dash-fact-value">{{ $customers['orders_per_buyer'] === null ? '—' : number_format($customers['orders_per_buyer'], 2) }}</span>
                            <span class="dash-fact-note">all time</span>
                        </li>
                        <li>
                            {{-- Accounts opened on the shop, kept separate: a
                                 marketplace order creates its customer
                                 without them registering here. --}}
                            <span class="dash-fact-label">Shop accounts</span>
                            <span class="dash-fact-value">{{ number_format($reference['customers']) }}</span>
                            <span class="dash-fact-note">registered here, of {{ number_format($customers['lifetime_buyers']) }} who have ordered</span>
                        </li>
                    </ul>
                </section>

                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Order pipeline</h3>
                        <a href="{{ route('admin.orders.index') }}" class="dash-panel-note">All orders</a>
                    </div>

                    {{-- Each stage wears the colour its status badge wears on
                         the orders list: one distinction to learn for both
                         pages. --}}
                    @if ($pipelineOpen->sum('count') > 0)
                        <div class="dash-stack" role="img" aria-label="Open orders by stage">
                            @foreach ($pipelineOpen as $stage)
                                @if ($stage['count'] > 0)
                                    <span
                                        class="dash-stack-segment dash-status-{{ $stage['status'] }}"
                                        style="--segment-width: {{ round($stage['count'] / $pipelineTotal * 100, 2) }}%"
                                    ></span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="dash-pipeline-clear">Nothing waiting. Every order is delivered or refunded.</p>
                    @endif

                    <ul class="dash-pipeline-list">
                        @foreach ($pipelineOpen as $stage)
                            <li>
                                <span class="dash-swatch dash-status-{{ $stage['status'] }}"></span>
                                <span class="dash-pipeline-label">{{ $stage['label'] }}</span>
                                <span class="dash-pipeline-count">{{ number_format($stage['count']) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    {{-- The end states, under a rule: counted, but out of the
                         bar they would crush. --}}
                    <ul class="dash-pipeline-list dash-pipeline-list--closed">
                        <li class="dash-pipeline-rule"><span>Closed</span></li>
                        @foreach ($pipelineClosed as $stage)
                            <li>
                                <span class="dash-swatch dash-status-{{ $stage['status'] }}"></span>
                                <span class="dash-pipeline-label">{{ $stage['label'] }}</span>
                                <span class="dash-pipeline-count">{{ number_format($stage['count']) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="dash-panel-foot">
                        <a href="{{ route('admin.orders.index', ['tab' => 'draft']) }}">{{ number_format($reference['drafts']) }} draft {{ \Illuminate\Support\Str::plural('order', $reference['drafts']) }}</a>
                        · <a href="{{ route('admin.orders.index') }}">{{ number_format($reference['external_orders']) }} manual {{ \Illuminate\Support\Str::plural('order', $reference['external_orders']) }}</a>
                    </p>
                </section>
            </div>

            <div class="dash-row dash-row--thirds">
                <section class="order-panel">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Recent orders</h3>
                        <a href="{{ route('admin.orders.index') }}" class="dash-panel-note">All orders</a>
                    </div>

                    @if ($recentOrders->isEmpty())
                        <p class="empty-state">No orders yet.</p>
                    @else
                        <ul class="dash-list dash-list--orders">
                            @foreach ($recentOrders as $order)
                                @php($units = (int) ($order->units_count ?? 0))
                                <li>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">{{ $order->number }}</a>
                                    <span class="admin-table-sub">
                                        {{ $order->user?->name ?? 'Guest' }} · {{ $order->created_at->format('d/m') }}
                                        · {{ number_format($units) }} {{ \Illuminate\Support\Str::plural('item', $units) }}
                                        {{-- Where the sale came from, at the end
                                             of the facts describing it rather
                                             than in a chip: six boxed DIRECTs
                                             fought the amount for the column
                                             and taught nobody anything. --}}
                                        · <span class="dash-order-channel">
                                            @if ($order->marketplace?->logo)
                                                <img src="{{ $order->marketplace->logoUrl() }}" alt="" class="dash-order-channel-logo" width="14" height="14" loading="lazy">
                                            @endif
                                            {{ filled($order->marketplace_name) ? $order->marketplace_name : 'Direct' }}
                                        </span>
                                    </span>
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

            @if ($stockAlertProducts->isNotEmpty())
                {{-- Seul panneau à porter un formulaire : il prend toute la
                     largeur, sinon quantité, raison et bouton se tassent dans
                     une colonne prévue pour des listes. --}}
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
