@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
    @php
        $baseFilters = array_filter([
            'search' => $search ?: null,
            'marketplace_id' => $marketplaceId ?: null,
            'date_from' => $dateFrom ?: null,
            'date_to' => $dateTo ?: null,
        ]);
        $hasFilters = $baseFilters !== [];
        // Changer d'onglet garde la recherche et les dates, jamais le statut :
        // c'est l'onglet lui-même qui le porte.
        $tabUrl = fn (array $overrides = []): string => route('admin.orders.index', [...$baseFilters, ...$overrides]);
        $filterUrl = function (array $overrides = []) use ($tab, $search, $status, $marketplaceId, $dateFrom, $dateTo): string {
            return route('admin.orders.index', array_filter([
                'tab' => $tab !== 'orders' ? $tab : null,
                'search' => array_key_exists('search', $overrides) ? $overrides['search'] : ($search ?: null),
                'status' => array_key_exists('status', $overrides) ? $overrides['status'] : ($status ?: null),
                'marketplace_id' => array_key_exists('marketplace_id', $overrides) ? $overrides['marketplace_id'] : $marketplaceId,
                'date_from' => array_key_exists('date_from', $overrides) ? $overrides['date_from'] : ($dateFrom ?: null),
                'date_to' => array_key_exists('date_to', $overrides) ? $overrides['date_to'] : ($dateTo ?: null),
            ]));
        };
        $activeMarketplace = $marketplaceId
            ? $marketplaces->firstWhere('id', $marketplaceId)
            : null;
    @endphp

    <div class="admin-list-page admin-orders-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Sales</p>
                    <h2 class="admin-list-title">Orders</h2>
                    <p class="admin-list-lede">Every order placed in the shop.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.orders.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
                    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($kpis['order_count']) }} total orders</span>
                <span class="admin-list-chip admin-list-chip--shipped">{{ number_format($kpis['shipped_count']) }} shipped</span>
                <span class="admin-list-chip admin-list-chip--in_transit">{{ number_format($kpis['in_transit_count']) }} in transit</span>
                <span class="admin-list-chip admin-list-chip--delivered">{{ number_format($kpis['delivered_count']) }} delivered</span>
                <span class="admin-list-chip admin-list-chip--refunded">{{ number_format($kpis['refunded_count']) }} refunded</span>
                <span class="admin-list-chip">{{ number_format($kpis['to_prepare_count']) }} to prepare</span>
                <span class="admin-list-chip">{{ number_format($kpis['missing_tracking_count']) }} missing tracking</span>
                @if ($hasFilters)
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        <div class="admin-stat-grid admin-stat-grid--primary">
            {{-- La base à laquelle tous les pourcentages voisins se rapportent.
                 Même structure que les deux blocs : un chiffre en tête, puis
                 des lignes qui le détaillent. --}}
            <div class="admin-stat-card admin-stat-card--headline">
                <span class="admin-stat-label">Total amount</span>
                <span class="admin-stat-value">{{ format_euros($kpis['amount_cents']) }}</span>
                <span class="admin-stat-pct-row">
                    <span class="admin-stat-pct" title="Every percentage on this row is a share of this total">100 % — the base</span>
                </span>
                <ul class="admin-stat-parts">
                    <li class="admin-stat-part">
                        <span class="admin-stat-part-name" title="Orders counted in this total">Orders</span>
                        <span class="admin-stat-part-value">{{ number_format($kpis['order_count']) }}</span>
                    </li>
                    <li class="admin-stat-part">
                        <span class="admin-stat-part-name" title="Total amount divided by the number of orders">Average order</span>
                        <span class="admin-stat-part-value">{{ format_euros($kpis['average_order_cents']) }}</span>
                    </li>
                </ul>
            </div>
            {{-- Les trois postes qui composent « Total costs » : groupés, ils se
                 lisent comme une décomposition plutôt que comme trois chiffres
                 indépendants, et la ligne tient en une seule rangée. --}}
            <div class="admin-stat-card admin-stat-card--breakdown">
                <span class="admin-stat-label">Cost breakdown</span>
                <ul class="admin-stat-parts">
                    @foreach ([
                        ['Own shipping', 'Paid out of pocket', 'shipping_cost'],
                        ['Commission', 'Marketplace cut', 'commission_cost'],
                        ['Payment fees', 'Card / PayPal processor', 'payment_fee'],
                    ] as [$partLabel, $partHint, $partKey])
                        <li class="admin-stat-part">
                            <span class="admin-stat-part-name" title="{{ $partHint }}">{{ $partLabel }}</span>
                            <span class="admin-stat-part-value">{{ format_euros($kpis[$partKey.'_cents']) }}</span>
                            <span class="admin-stat-part-pcts">
                                <span class="admin-stat-pct">{{ number_format($kpis[$partKey.'_pct_amount'] ?? 0, 2) }} % of amount</span>
                                <span class="admin-stat-pct">{{ number_format($kpis[$partKey.'_pct_costs'] ?? 0, 2) }} % of costs</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            {{-- Ce qui reste une fois les coûts retirés : les trois chiffres se
                 déduisent l'un de l'autre, ils se lisent donc ensemble. Seul le
                 montant porte la couleur ; le bloc reste neutre, comme celui
                 des coûts à côté. --}}
            <div class="admin-stat-card admin-stat-card--breakdown">
                <span class="admin-stat-label">Results</span>
                <ul class="admin-stat-parts">
                    <li class="admin-stat-part">
                        <span class="admin-stat-part-name" title="Shipping + commission + fees">Total costs</span>
                        <span class="admin-stat-part-value is-cost">{{ format_euros($kpis['total_costs_cents']) }}</span>
                        <span class="admin-stat-part-pcts">
                            <span class="admin-stat-pct">{{ number_format($kpis['total_costs_pct_amount'] ?? 0, 2) }} % of amount</span>
                        </span>
                    </li>
                    <li class="admin-stat-part">
                        <span class="admin-stat-part-name" title="Order totals after all costs">Total perceived</span>
                        <span class="admin-stat-part-value is-kept">{{ format_euros($kpis['perceived_total_cents']) }}</span>
                        <span class="admin-stat-part-pcts">
                            <span class="admin-stat-pct">{{ number_format($kpis['perceived_total_pct_amount'] ?? 0, 2) }} % of amount</span>
                        </span>
                    </li>
                    {{-- Un coût produit inconnu exclut la commande plutôt que de
                         compter zéro : le compteur dit combien sont concernées,
                         comme le tiret sur chaque ligne de la liste. --}}
                    <li class="admin-stat-part">
                        <span class="admin-stat-part-name" title="Perceived − product cost. Orders with an unknown product cost are left out.">Profit</span>
                        <span class="admin-stat-part-value is-profit">{{ format_euros($kpis['profit_cents']) }}</span>
                        <span class="admin-stat-part-pcts">
                            <span class="admin-stat-pct">on {{ number_format($kpis['profit_priced_order_count']) }} of {{ number_format($kpis['profit_total_order_count']) }} orders</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <nav class="admin-tabs" aria-label="Order tabs">
            <a href="{{ $tabUrl() }}" class="{{ $tab === 'orders' && $status === '' ? 'active' : '' }}">
                Orders <span class="admin-tab-count">{{ number_format($orderCount) }}</span>
            </a>
            <a href="{{ $tabUrl(['tab' => 'draft']) }}" class="{{ $tab === 'draft' ? 'active' : '' }}">
                Drafts <span class="admin-tab-count">{{ number_format($draftCount) }}</span>
            </a>
            @foreach ($statuses as $index => $statusOption)
                <a
                    href="{{ $tabUrl(['status' => $statusOption]) }}"
                    class="{{ $index === 0 ? 'starts-group ' : '' }}{{ $tab === 'orders' && $status === $statusOption ? 'active' : '' }}"
                >
                    {{ \App\Models\Order::labelForStatus($statusOption) }}
                    <span class="admin-tab-count">{{ number_format($statusCounts[$statusOption] ?? 0) }}</span>
                </a>
            @endforeach
            <a href="{{ $tabUrl(['tab' => 'archived']) }}" class="sits-apart {{ $tab === 'archived' ? 'active' : '' }}">
                Archived <span class="admin-tab-count">{{ number_format($archivedCount) }}</span>
            </a>
            <a href="{{ $tabUrl(['tab' => 'test']) }}" class="{{ $tab === 'test' ? 'active' : '' }}">
                Test <span class="admin-tab-count">{{ number_format($testCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="status" value="{{ $status }}">

            <div class="admin-filter-search">
                <label class="admin-filter-label" for="order-search">Search</label>
                <input
                    id="order-search"
                    type="search"
                    name="search"
                    class="form-control admin-toolbar-search"
                    placeholder="Order number, customer or email…"
                    value="{{ $search }}"
                >
            </div>

            <div class="admin-filter-row">
                <div class="admin-filter-field">
                    <label class="admin-filter-label" for="order-marketplace">Marketplace</label>
                    <select id="order-marketplace" name="marketplace_id" class="form-control">
                        <option value="">All marketplaces</option>
                        @foreach ($marketplaces as $marketplace)
                            <option value="{{ $marketplace->id }}" @selected($marketplaceId === $marketplace->id)>{{ $marketplace->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-field admin-filter-field--dates">
                    <span class="admin-filter-label" id="order-dates-label">Placed between</span>
                    <div class="admin-filter-date-range" role="group" aria-labelledby="order-dates-label">
                        <label class="sr-only" for="order-date-from">From</label>
                        <input
                            id="order-date-from"
                            type="date"
                            name="date_from"
                            class="form-control"
                            value="{{ $dateFrom }}"
                        >
                        <span class="admin-filter-date-sep" aria-hidden="true">to</span>
                        <label class="sr-only" for="order-date-to">To</label>
                        <input
                            id="order-date-to"
                            type="date"
                            name="date_to"
                            class="form-control"
                            value="{{ $dateTo }}"
                        >
                    </div>
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if ($hasFilters)
                        <a href="{{ route('admin.orders.index', array_filter(['tab' => $tab !== 'orders' ? $tab : null, 'status' => $status ?: null])) }}" class="btn btn-secondary">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($hasFilters)
            <div class="admin-filter-chips" aria-label="Active filters">
                @if ($search !== '')
                    <a href="{{ $filterUrl(['search' => null]) }}" class="admin-filter-chip">
                        Search · {{ $search }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($activeMarketplace)
                    <a href="{{ $filterUrl(['marketplace_id' => null]) }}" class="admin-filter-chip">
                        Marketplace · {{ $activeMarketplace->name }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($dateFrom !== '')
                    <a href="{{ $filterUrl(['date_from' => null]) }}" class="admin-filter-chip">
                        From · {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d M Y') }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($dateTo !== '')
                    <a href="{{ $filterUrl(['date_to' => null]) }}" class="admin-filter-chip">
                        To · {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d M Y') }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
            </div>
        @endif

        @php
            $tabLabel = match ($tab) {
                'draft' => 'drafts',
                'archived' => 'archived orders',
                'test' => 'test orders',
                default => 'orders',
            };
        @endphp

        @if ($orders->isEmpty())
            <p class="empty-state">
                @if ($hasFilters)
                    No {{ $tabLabel }} match these filters.
                @else
                    No {{ $tabLabel }} yet.
                @endif
            </p>
        @else
            <p class="admin-result-count">
                Showing {{ $orders->firstItem() }}–{{ $orders->lastItem() }} of {{ number_format($orders->total()) }}
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table admin-orders-table">
                    <thead>
                        <tr>
                            <th class="admin-select-cell">
                                <input type="checkbox" id="bulk-select-all" aria-label="Select every order on this page">
                            </th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Shipping</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Free del.</th>
                            <th>Tracking</th>
                            <th class="admin-table-num">Total</th>
                            <th class="admin-table-num">Costs</th>
                            <th class="admin-table-num">P. costs</th>
                            <th class="admin-table-num">Perceived</th>
                            <th class="admin-table-num">Profit</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            @php($missingInvoiceFields = array_filter([$order->tracking_carrier_id === null ? 'Tracking carrier' : null, blank($order->tracking_number) ? 'Tracking number' : null, $order->package_type_id === null ? 'Package type' : null]))
                            <tr data-bulk-row>
                                <td class="admin-select-cell">
                                    <input
                                        type="checkbox"
                                        class="bulk-select-row"
                                        value="{{ $order->id }}"
                                        aria-label="Select order {{ $order->number }}"
                                    >
                                </td>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">
                                        {{ $order->number }}
                                    </a>
                                    @if ($order->isTest())
                                        <span class="order-chip order-chip--test" title="Kept as a record of testing; left out of every figure">Test</span>
                                    @endif
                                    <span class="admin-table-sub">{{ $order->created_at->format('d M Y · H:i') }}</span>
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ $order->user?->name ?? '—' }}</span>
                                    @if ($order->user?->email)
                                        <span class="admin-table-sub">{{ $order->user->email }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-table-primary">
                                        {{ $order->carrierName() !== '' ? $order->carrierName() : '—' }}
                                    </span>
                                    <span class="admin-table-sub">
                                        {{ $order->payment_method?->label() ?? '—' }}
                                        · {{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($order->is_manual && $order->marketplace?->logo)
                                        <span class="order-chip order-chip--channel order-chip--channel-logo" title="{{ $order->marketplace_name }}">
                                            <img src="{{ $order->marketplace->logoUrl() }}" alt="{{ $order->marketplace_name }}" class="marketplace-logo marketplace-logo--md">
                                        </span>
                                    @else
                                        <span class="admin-table-sub">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="order-chip order-chip--{{ $order->status }}">
                                        {{ $order->statusLabel() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($order->deliveryWasFree() && $order->deliveryWasFreedByCode())
                                        <span class="order-chip order-chip--shipped" title="Waived by discount code {{ $order->discountCodeCode() }}">Yes (code)</span>
                                    @elseif ($order->deliveryWasFree())
                                        <span class="order-chip order-chip--shipped">Yes</span>
                                    @else
                                        <span class="order-chip order-chip--draft">No</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($order->hasTracking())
                                        <span class="order-chip order-chip--tracking-available">Available</span>
                                    @elseif ($order->hasBeenShipped())
                                        <span class="order-chip order-chip--tracking-missing">Missing</span>
                                    @else
                                        <span class="order-chip order-chip--tracking-na">N/A</span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    <span class="admin-order-total">{{ $order->formattedTotal() }}</span>
                                    @if ($order->hasRecordedCosts())
                                        <span class="admin-order-deductions">
                                            {{-- !== null et non pas « non vide » : un coût saisi à
                                                 zéro doit s'afficher, sinon il ressemble à un champ
                                                 qu'on aurait oublié de remplir. --}}
                                            @if ($order->marketplace_commission_cents !== null)
                                                <span class="admin-order-deduction" title="Commission">−{{ format_euros($order->marketplace_commission_cents) }} comm.</span>
                                            @endif
                                            @if ($order->shipping_paid_cents !== null)
                                                <span class="admin-order-deduction" title="Shipping paid">−{{ format_euros($order->shipping_paid_cents) }} ship.</span>
                                            @endif
                                            @if ($order->payment_fee_cents !== null)
                                                <span class="admin-order-deduction" title="{{ $order->payment_method?->label() }} fee">−{{ format_euros($order->payment_fee_cents) }} fee</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    @if ($order->hasRecordedCosts())
                                        <span class="stripe-fee-chip">− {{ $order->formattedTotalCosts() }}</span>
                                    @else
                                        <span class="admin-table-sub">—</span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    @php($productCostCents = $order->productCostInclVatCents($productCostsByProductId))
                                    @if ($productCostCents !== null)
                                        <span class="stripe-fee-chip" title="Average purchase cost, incl. VAT">− {{ format_euros($productCostCents) }}</span>
                                    @else
                                        <span class="admin-table-sub" title="Missing purchase history for at least one line">—</span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    <span class="admin-order-perceived">{{ $order->formattedPerceivedTotal() }}</span>
                                </td>
                                <td class="admin-table-num">
                                    @php($profitCents = $order->profitInclVatCents($productCostsByProductId))
                                    @if ($profitCents !== null)
                                        <span class="admin-order-profit">{{ format_euros($profitCents) }}</span>
                                    @else
                                        <span class="admin-table-sub" title="Missing purchase history for at least one line">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-actions-menu">
                                        <button type="button" class="admin-actions-trigger" data-actions-toggle aria-haspopup="true" aria-expanded="false">
                                            Actions
                                            <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                                                <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        <div class="admin-actions-dropdown" data-actions-dropdown hidden>
                                            @if (! $order->isDraft())
                                                <a href="{{ route('admin.orders.delivery-slip', $order) }}" class="admin-actions-item">
                                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    Download delivery slip
                                                </a>
                                            @else
                                                <span class="admin-actions-item is-disabled" title="Not available for drafts">
                                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    Download delivery slip
                                                </span>
                                            @endif

                                            @if ($order->adminInvoiceIsAvailable())
                                                <a
                                                    href="{{ route('admin.orders.invoice', $order) }}"
                                                    class="admin-actions-item"
                                                    @if ($missingInvoiceFields !== [])
                                                        data-invoice-confirm
                                                        data-missing="{{ implode(',', $missingInvoiceFields) }}"
                                                    @endif
                                                >
                                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    Download invoice
                                                </a>
                                            @else
                                                <span class="admin-actions-item is-disabled" title="Invoice not available yet">
                                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    Download invoice
                                                </span>
                                            @endif

                                            @if (auth()->user()->isOwner() && $order->canBeMarkedAsTest())
                                            <button
                                                type="button"
                                                class="admin-actions-item"
                                                data-confirm-toggle
                                                data-action="{{ route($order->isTest() ? 'admin.orders.untest' : 'admin.orders.test', $order) }}"
                                                data-body="{{ $order->isTest()
                                                    ? 'Order '.$order->number.' will count towards the figures again.'
                                                    : 'Order '.$order->number.' will be kept but left out of every figure. Stock and invoice numbers it used are not given back.' }}"
                                                data-label="{{ $order->isTest() ? 'Unmark as test' : 'Mark as test' }}"
                                            >
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                    <path d="M9 3h6M10 3v6.2L5.5 18a2 2 0 0 0 1.8 3h9.4a2 2 0 0 0 1.8-3L14 9.2V3" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                {{ $order->isTest() ? 'Unmark as test' : 'Mark as test' }}
                                            </button>
                                            @endif

                                            @if ($order->canBeDeleted())
                                                @if (auth()->user()->isOwner())
                                                    <button
                                                        type="button"
                                                        class="admin-actions-item is-danger"
                                                        data-confirm-toggle
                                                        data-action="{{ route('admin.orders.destroy', $order) }}"
                                                        data-method="DELETE"
                                                        data-body="Draft {{ $order->number }} will be deleted for good, along with its lines. This cannot be undone."
                                                        data-label="Delete draft"
                                                    >
                                                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                            <path d="M5 7h14M10 7V5h4v2m-7 0 .8 12a2 2 0 0 0 2 1.9h4.4a2 2 0 0 0 2-1.9L17 7" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                        Delete draft
                                                    </button>
                                                @endif
                                            @else
                                            <button
                                                type="button"
                                                class="admin-actions-item"
                                                data-confirm-toggle
                                                data-action="{{ route($order->isArchived() ? 'admin.orders.unarchive' : 'admin.orders.archive', $order) }}"
                                                data-body="Are you sure you want to {{ $order->isArchived() ? 'unarchive' : 'archive' }} order {{ $order->number }}?"
                                                data-label="{{ $order->isArchived() ? 'Unarchive order' : 'Archive order' }}"
                                            >
                                                @if ($order->isArchived())
                                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                        <rect x="4" y="4" width="16" height="4" rx="1" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                        <path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                        <path d="M14 12.5 12 10.5 10 12.5m2-2v6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    Unarchive order
                                                @else
                                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                        <rect x="4" y="4" width="16" height="4" rx="1" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                        <path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                        <path d="M10 14.5 12 16.5l2-2m-2 2v-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    Archive order
                                                @endif
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $orders])

            <div class="bulk-bar" id="bulk-bar" hidden>
                <span class="bulk-bar-count"><span id="bulk-bar-number">0</span> selected</span>
                <div class="bulk-bar-actions">
                    <button type="button" class="btn btn-sm btn-secondary" id="bulk-clear">Clear</button>
                    @if ($tab === 'draft')
                        @if (auth()->user()->isOwner())
                            <button
                                type="button"
                                class="btn btn-sm btn-danger"
                                data-bulk-apply
                                data-bulk-action="{{ route('admin.orders.bulk-destroy') }}"
                                data-bulk-method="DELETE"
                                data-bulk-verb="permanently delete"
                                data-bulk-label="Delete"
                            >Delete</button>
                        @endif
                    @elseif ($tab === 'test' && auth()->user()->isOwner())
                        <button
                            type="button"
                            class="btn btn-sm btn-primary"
                            data-bulk-apply
                            data-bulk-action="{{ route('admin.orders.bulk-untest') }}"
                            data-bulk-verb="unmark as test"
                            data-bulk-label="Unmark as test"
                        >Unmark as test</button>
                    @else
                        @if (auth()->user()->isOwner())
                        <button
                            type="button"
                            class="btn btn-sm btn-secondary"
                            data-bulk-apply
                            data-bulk-action="{{ route('admin.orders.bulk-test') }}"
                            data-bulk-verb="mark as test"
                            data-bulk-label="Mark as test"
                        >Mark as test</button>
                        @endif
                        <button
                            type="button"
                            class="btn btn-sm btn-primary"
                            data-bulk-apply
                            data-bulk-action="{{ route($tab === 'archived' ? 'admin.orders.bulk-unarchive' : 'admin.orders.bulk-archive') }}"
                            data-bulk-verb="{{ $tab === 'archived' ? 'unarchive' : 'archive' }}"
                            data-bulk-label="{{ $tab === 'archived' ? 'Unarchive' : 'Archive' }}"
                        >{{ $tab === 'archived' ? 'Unarchive' : 'Archive' }}</button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Shared across every row, like row-confirm-modal: the link's own
             data attributes carry the specifics, so one dialog covers every
             order rather than one per row. --}}
        <dialog id="invoice-warning-modal" class="modal" aria-labelledby="invoice-warning-title">
            <h3 class="modal-title" id="invoice-warning-title">Shipping details are incomplete</h3>
            <div class="order-invoice-warning">
                <p class="order-invoice-warning-lede">Missing before this order can be shipped:</p>
                <ul class="order-invoice-warning-list" id="invoice-warning-list"></ul>
            </div>
            <p class="modal-body">
                You can still download the invoice now, but it will go out without these details.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <a href="#" id="invoice-warning-download" class="btn btn-primary">Download anyway</a>
            </div>
        </dialog>

        <dialog id="row-confirm-modal" class="modal" aria-labelledby="row-confirm-title">
            <form method="POST" id="row-confirm-form">
                @csrf
                @method('PATCH')
                <h3 class="modal-title" id="row-confirm-title">Are you sure?</h3>
                <p class="modal-body" id="row-confirm-body"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="row-confirm-submit"></button>
                </div>
            </form>
        </dialog>

        {{-- The bulk action has its own modal rather than borrowing the
             per-row one: sharing meant reassigning that modal's submit
             handler, and any close path that failed to clear it would have
             let a later per-row click submit a stale bulk selection. --}}
        <dialog id="bulk-confirm-modal" class="modal" aria-labelledby="bulk-confirm-title">
            {{-- The action is set by whichever bulk button was pressed, since
                 this bar now offers more than one. --}}
            <form method="POST" id="bulk-confirm-form">
                @csrf
                @method('PATCH')
                <h3 class="modal-title" id="bulk-confirm-title">Are you sure?</h3>
                <p class="modal-body" id="bulk-confirm-body"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulk-confirm-submit"></button>
                </div>
            </form>
        </dialog>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('[data-actions-toggle]').forEach(function (trigger) {
                var dropdown = trigger.nextElementSibling;

                trigger.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var isOpen = !dropdown.hidden;

                    document.querySelectorAll('[data-actions-dropdown]').forEach(function (el) {
                        el.hidden = true;
                        el.previousElementSibling.setAttribute('aria-expanded', 'false');
                    });

                    if (!isOpen) {
                        dropdown.hidden = false;
                        trigger.setAttribute('aria-expanded', 'true');
                    }
                });
            });

            document.addEventListener('click', function () {
                document.querySelectorAll('[data-actions-dropdown]').forEach(function (el) {
                    el.hidden = true;
                    el.previousElementSibling.setAttribute('aria-expanded', 'false');
                });
            });
        })();
    </script>
    <script>
        (function () {
            var invoiceModal = document.getElementById('invoice-warning-modal');
            var invoiceList = document.getElementById('invoice-warning-list');
            var invoiceDownload = document.getElementById('invoice-warning-download');

            document.querySelectorAll('[data-invoice-confirm]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();

                    invoiceList.innerHTML = '';
                    link.getAttribute('data-missing').split(',').forEach(function (field) {
                        var item = document.createElement('li');
                        item.textContent = field;
                        invoiceList.appendChild(item);
                    });
                    invoiceDownload.href = link.getAttribute('href');

                    invoiceModal.showModal();
                });
            });

            var modal = document.getElementById('row-confirm-modal');
            var form = document.getElementById('row-confirm-form');
            var body = document.getElementById('row-confirm-body');
            var submitBtn = document.getElementById('row-confirm-submit');

            // Copy comes from the button rather than being computed here, so
            // one modal serves archiving and test-marking alike.
            document.querySelectorAll('[data-confirm-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    form.action = btn.getAttribute('data-action');
                    // Archiving patches, deleting deletes.
                    form.querySelector('[name="_method"]').value = btn.getAttribute('data-method') || 'PATCH';
                    body.textContent = btn.getAttribute('data-body');
                    submitBtn.textContent = btn.getAttribute('data-label');
                    submitBtn.classList.toggle('btn-danger', btn.getAttribute('data-method') === 'DELETE');

                    modal.showModal();
                });
            });
        })();
    </script>
    <script src="{{ asset('js/admin-bulk-select.js') }}" defer></script>
@endpush
