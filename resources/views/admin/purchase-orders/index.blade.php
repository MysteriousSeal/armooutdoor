@extends('layouts.admin')

@section('title', 'Purchase orders')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Inventory</p>
                    <h2 class="admin-list-title">Purchase orders</h2>
                    <p class="admin-list-lede">Stock ordered from suppliers, received as it arrives.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">New purchase order</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($openCount) }} open</span>
                <span class="admin-list-chip">{{ number_format($unitsOnOrder) }} units on order</span>
                <span class="admin-list-chip">{{ format_euros($committedCostCents) }} committed (excl. VAT)</span>
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Purchase order tabs">
            <a href="{{ route('admin.purchase-orders.index', ['tab' => 'open']) }}" class="{{ $tab === 'open' ? 'active' : '' }}">
                Open <span class="admin-tab-count">{{ number_format($openCount) }}</span>
            </a>
            <a href="{{ route('admin.purchase-orders.index', ['tab' => 'draft']) }}" class="{{ $tab === 'draft' ? 'active' : '' }}">
                Drafts <span class="admin-tab-count">{{ number_format($draftCount) }}</span>
            </a>
            <a href="{{ route('admin.purchase-orders.index', ['tab' => 'received']) }}" class="{{ $tab === 'received' ? 'active' : '' }}">
                Received <span class="admin-tab-count">{{ number_format($receivedCount) }}</span>
            </a>
            <a href="{{ route('admin.purchase-orders.index', ['tab' => 'cancelled']) }}" class="{{ $tab === 'cancelled' ? 'active' : '' }}">
                Cancelled <span class="admin-tab-count">{{ number_format($cancelledCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="admin-filter-field admin-filter-field--search">
                <label class="admin-filter-label" for="po-search">Search</label>
                <input
                    id="po-search"
                    type="search"
                    name="search"
                    class="form-control admin-toolbar-search"
                    placeholder="Number, supplier or reference…"
                    value="{{ $search }}"
                >
            </div>
            <div class="admin-filter-field">
                <label class="admin-filter-label" for="po-supplier">Supplier</label>
                <select id="po-supplier" name="supplier_id" class="form-control">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierId === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>

        @if ($purchaseOrders->isEmpty())
            <p class="empty-state">
                @switch($tab)
                    @case('draft') No draft purchase orders. @break
                    @case('received') Nothing fully received yet. @break
                    @case('cancelled') No cancelled purchase orders. @break
                    @default Nothing on order right now.
                @endswitch
            </p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Lines</th>
                            <th>Received</th>
                            <th>Total (excl. VAT)</th>
                            <th>Expected</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchaseOrders as $po)
                            @php
                                $ordered = $po->items->sum('quantity_ordered');
                                $received = $po->items->sum('quantity_received');
                            @endphp
                            <tr>
                                <td><a href="{{ route('admin.purchase-orders.show', $po) }}" class="admin-link">{{ $po->number }}</a></td>
                                <td>{{ $po->supplier_name }}</td>
                                <td><span class="po-status-badge is-{{ str_replace('_', '-', $po->status) }}">{{ ucfirst(str_replace('_', ' ', $po->status)) }}</span></td>
                                <td>{{ $po->items->count() }}</td>
                                <td>
                                    <span class="po-received-ratio {{ $received >= $ordered && $ordered > 0 ? 'is-complete' : ($received > 0 ? 'is-partial' : '') }}">
                                        {{ $received }}/{{ $ordered }}
                                    </span>
                                </td>
                                <td>{{ format_euros($po->totalCents()) }}</td>
                                <td>{{ $po->expected_at?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $po->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $purchaseOrders])
        @endif
    </div>
@endsection
