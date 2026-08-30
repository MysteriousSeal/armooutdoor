@extends('layouts.admin')

@section('title', 'Analytics')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Traffic</p>
            <h2 class="admin-list-title">Analytics</h2>
            <p class="admin-list-lede">
                Visit breakdowns and a detailed log of recent traffic.
            </p>
            <div class="admin-list-meta">
                <span class="admin-list-chip admin-active-now-chip {{ $activeNow['total'] > 0 ? 'is-live' : '' }}" title="Distinct visitors with a page view in the last 2 minutes (bots counted separately)">
                    <span class="admin-active-now-dot" aria-hidden="true"></span>
                    <span class="admin-active-now-count">{{ number_format($activeNow['total']) }}</span>
                    active
                    <span class="admin-list-chip-muted">
                        · {{ number_format($activeNow['users']) }} logged in
                        · {{ number_format($activeNow['guests']) }} {{ \Illuminate\Support\Str::plural('guest', $activeNow['guests']) }}
                        · {{ number_format($activeNow['bots']) }} {{ \Illuminate\Support\Str::plural('bot', $activeNow['bots']) }}
                    </span>
                </span>
            </div>
        </header>

        <nav class="admin-list-tabs" aria-label="Time range">
            @foreach ($ranges as $key => $label)
                <a
                    href="{{ route('admin.analytics.index', array_filter(['range' => $key, 'bots' => $hideBots ? 'hide' : null])) }}"
                    class="admin-list-tab {{ $range === $key ? 'active' : '' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <section class="admin-analytics-stats" aria-label="Summary for {{ $ranges[$range] }}">
            <div class="admin-analytics-stat">
                <span class="admin-analytics-stat-label">Page views</span>
                <span class="admin-analytics-stat-value">{{ number_format($visits->total()) }}</span>
            </div>
            <div class="admin-analytics-stat">
                <span class="admin-analytics-stat-label">Unique visitors</span>
                <span class="admin-analytics-stat-value">{{ number_format($rangeVisitors['total']) }}</span>
            </div>
            <div class="admin-analytics-stat">
                <span class="admin-analytics-stat-label">Logged in</span>
                <span class="admin-analytics-stat-value">{{ number_format($rangeVisitors['users']) }}</span>
            </div>
            <div class="admin-analytics-stat">
                <span class="admin-analytics-stat-label">Guests</span>
                <span class="admin-analytics-stat-value">{{ number_format($rangeVisitors['guests']) }}</span>
            </div>
            <div class="admin-analytics-stat">
                <span class="admin-analytics-stat-label">Bots</span>
                <span class="admin-analytics-stat-value">{{ number_format($rangeVisitors['bots']) }}</span>
            </div>
            <div class="admin-analytics-stat admin-analytics-stat-live {{ $activeNow['total'] > 0 ? 'is-live' : '' }}">
                <span class="admin-analytics-stat-label">Active now</span>
                <span class="admin-analytics-stat-value">
                    <span class="admin-active-now-dot" aria-hidden="true"></span>
                    {{ number_format($activeNow['total']) }}
                </span>
            </div>
        </section>

        @if ($visits->isEmpty())
            <p class="empty-state">
                @if ($range === 'all')
                    No visits recorded yet.
                @else
                    No visits in {{ strtolower($ranges[$range]) }}.
                    <a href="{{ route('admin.analytics.index', ['range' => 'all']) }}">View all time</a>
                @endif
            </p>
        @else

            @if (! empty($series))
                <section class="order-panel dash-chart-panel admin-analytics-section" aria-label="Visits over time for {{ $ranges[$range] }}">
                    <div class="dash-panel-head">
                        <h3 class="order-panel-title">Visits over time</h3>
                        <span class="dash-legend">
                            <span class="dash-legend-item"><span class="dash-swatch dash-swatch--current"></span>Humans</span>
                            <span class="dash-legend-item"><span class="dash-swatch dash-swatch--previous"></span>Bots</span>
                        </span>
                    </div>

                    <div
                        class="dash-chart"
                        data-visits-chart
                        data-humans="{{ json_encode(collect($series)->pluck('humans')->all()) }}"
                        data-bots="{{ json_encode(collect($series)->pluck('bots')->all()) }}"
                        data-labels="{{ json_encode(collect($series)->pluck('label')->all()) }}"
                    >
                        <canvas height="220" aria-hidden="true"></canvas>
                    </div>

                    <details class="dash-table-view">
                        <summary>Table view</summary>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <caption class="sr-only">Page views over time, humans and bots apart</caption>
                                <thead>
                                    <tr>
                                        <th>{{ $range === '24h' ? 'Hour' : ($range === 'all' ? 'Month' : 'Day') }}</th>
                                        <th class="admin-table-num">Humans</th>
                                        <th class="admin-table-num">Bots</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($series as $point)
                                        <tr>
                                            <td>{{ $point['label'] }}</td>
                                            <td class="admin-table-num">{{ number_format($point['humans']) }}</td>
                                            <td class="admin-table-num">{{ number_format($point['bots']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </section>
            @endif

            @if (! empty($topPages) || ! empty($topReferrers))
                <div class="admin-analytics-tops">
                    <section class="order-panel">
                        <div class="dash-panel-head">
                            <h3 class="order-panel-title">Top pages</h3>
                            <span class="dash-panel-note">human views · {{ strtolower($ranges[$range]) }}</span>
                        </div>
                        @if (empty($topPages))
                            <p class="empty-state">No human page views in this range.</p>
                        @else
                            <table class="admin-table">
                                <tbody>
                                    @foreach ($topPages as $page)
                                        <tr>
                                            <td class="admin-analytics-top-path" title="{{ $page['path'] }}">{{ $page['path'] }}</td>
                                            <td class="admin-table-num">{{ number_format($page['count']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>

                    <section class="order-panel">
                        <div class="dash-panel-head">
                            <h3 class="order-panel-title">Top referrers</h3>
                            <span class="dash-panel-note">external · {{ strtolower($ranges[$range]) }}</span>
                        </div>
                        @if (empty($topReferrers))
                            <p class="empty-state">No external referrers in this range.</p>
                        @else
                            <table class="admin-table">
                                <tbody>
                                    @foreach ($topReferrers as $referrer)
                                        <tr>
                                            <td class="admin-analytics-top-path" title="{{ $referrer['host'] }}">{{ $referrer['host'] }}</td>
                                            <td class="admin-table-num">{{ number_format($referrer['count']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>
                </div>
            @endif

            @if (! empty($topProducts) || ! empty($topCategories))
                <div class="admin-analytics-tops">
                    <section class="order-panel">
                        <div class="dash-panel-head">
                            <h3 class="order-panel-title">Top products</h3>
                            <span class="dash-panel-note">human views · {{ strtolower($ranges[$range]) }}</span>
                        </div>
                        @if (empty($topProducts))
                            <p class="empty-state">No product pages viewed in this range.</p>
                        @else
                            <table class="admin-table">
                                <tbody>
                                    @foreach ($topProducts as $product)
                                        <tr>
                                            <td class="admin-analytics-top-path admin-analytics-top-path--name">
                                                <a href="{{ route('admin.products.edit', $product['id']) }}">{{ $product['name'] }}</a>
                                            </td>
                                            <td class="admin-table-num">{{ number_format($product['count']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>

                    <section class="order-panel">
                        <div class="dash-panel-head">
                            <h3 class="order-panel-title">Top categories</h3>
                            <span class="dash-panel-note">human views · {{ strtolower($ranges[$range]) }}</span>
                        </div>
                        @if (empty($topCategories))
                            <p class="empty-state">No category pages viewed in this range.</p>
                        @else
                            <table class="admin-table">
                                <tbody>
                                    @foreach ($topCategories as $category)
                                        <tr>
                                            <td class="admin-analytics-top-path admin-analytics-top-path--name">
                                                <a href="{{ route('admin.categories.edit', $category['id']) }}">{{ $category['name'] }}</a>
                                            </td>
                                            <td class="admin-table-num">{{ number_format($category['count']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>
                </div>
            @endif

            @if (! empty($charts))
                <section class="admin-analytics-section" aria-label="Visit breakdown charts for {{ $ranges[$range] }}">
                    <header class="admin-analytics-section-header">
                        <h3 class="admin-analytics-section-title">Breakdown</h3>
                        <p class="admin-analytics-section-desc">Who and what showed up · {{ $ranges[$range] }}</p>
                    </header>
                    <div class="admin-analytics-charts">
                        @foreach ($charts as $chart)
                            @include('admin.partials.donut-chart', ['chart' => $chart])
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="admin-analytics-section">
                <header class="admin-analytics-section-header admin-analytics-section-header-row">
                    <div>
                        <h3 class="admin-analytics-section-title">Visit log</h3>
                        <p class="admin-analytics-section-desc">
                            Page views for {{ strtolower($ranges[$range]) }}.
                        </p>
                    </div>
                    <div class="admin-analytics-log-tools">
                        <a
                            href="{{ route('admin.analytics.index', array_filter(['range' => $range, 'bots' => $hideBots ? null : 'hide'])) }}"
                            class="admin-list-chip admin-analytics-bots-toggle {{ $hideBots ? 'is-on' : '' }}"
                        >
                            {{ $hideBots ? 'Bots hidden' : 'Hide bots' }}
                        </a>
                        <p class="admin-result-count admin-analytics-result-count">
                            {{ $visits->firstItem() }}&ndash;{{ $visits->lastItem() }}
                            of {{ number_format($visits->total()) }}
                        </p>
                    </div>
                </header>

                <div class="admin-visits-table">
                    <div class="admin-visits-head" aria-hidden="true">
                        <span>Date &amp; time</span>
                        <span>Path</span>
                        <span>Referrer</span>
                        <span>Location</span>
                        <span>Browser</span>
                        <span>Device</span>
                        <span>OS</span>
                        <span>Bot</span>
                        <span>IP address</span>
                        <span>Customer</span>
                    </div>

                    <ul class="admin-visits-list">
                        @foreach ($visits as $visit)
                            @php
                                $isBursty = isset($burstyIps[$visit->ip_address ?: 'unknown']);
                                $isBot = $visit->is_bot || $isBursty;
                            @endphp
                            <li class="admin-visits-row {{ $isBot ? 'is-bot' : '' }}">
                                <time class="admin-visits-date" datetime="{{ $visit->created_at->toIso8601String() }}">
                                    {{ $visit->created_at->format('M j, Y g:i A') }}
                                </time>
                                <span class="admin-visits-path" @if ($visit->path) title="{{ $visit->path }}" @endif>
                                    {{ $visit->path ?: '—' }}
                                </span>
                                <span class="admin-visits-referrer" @if ($visit->referrer) title="{{ $visit->referrer }}" @endif>
                                    @if ($visit->referrer)
                                        @php
                                            $referrerHost = parse_url($visit->referrer, PHP_URL_HOST);
                                            $referrerLabel = $referrerHost
                                                ? \Illuminate\Support\Str::of($referrerHost)->replaceStart('www.', '')->toString()
                                                : \Illuminate\Support\Str::limit($visit->referrer, 40);
                                        @endphp
                                        {{ $referrerLabel }}
                                    @else
                                        &mdash;
                                    @endif
                                </span>
                                <span class="admin-visits-location" title="{{ $visit->location_label }}">
                                    {{ $visit->location_label }}
                                </span>
                                <span class="admin-visits-browser" @if ($visit->user_agent) title="{{ $visit->user_agent }}" @endif>
                                    {{ $visit->browser }}
                                </span>
                                <span class="admin-visits-device">
                                    {{ $visit->device }}
                                </span>
                                <span class="admin-visits-os">
                                    {{ $visit->os }}
                                </span>
                                <span class="admin-visits-bot">
                                    @if ($isBot)
                                        <span
                                            class="badge badge-bot"
                                            @if ($isBursty && ! $visit->is_bot)
                                                title="Flagged by request rate: this IP made {{ $burstThreshold }}+ page loads within {{ $burstWindowSeconds }} seconds"
                                            @endif
                                        >Bot</span>
                                    @else
                                        <span class="admin-visits-bot-no">No</span>
                                    @endif
                                </span>
                                <span class="admin-visits-ip">
                                    {{ $visit->ip_address ?: '—' }}
                                </span>
                                <span class="admin-visits-username">
                                    @if ($visit->user)
                                        <a href="{{ route('admin.customers.show', $visit->user) }}" target="_blank" rel="noopener noreferrer">{{ $visit->user->name }}</a>
                                    @else
                                        &mdash;
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @include('admin.partials.pager', ['paginator' => $visits])
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/vendor/chart.umd.min.js') }}" defer></script>
    <script src="{{ versioned_asset('js/admin-analytics-chart.js') }}" defer></script>
@endpush
