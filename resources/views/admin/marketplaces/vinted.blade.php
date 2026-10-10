@extends('layouts.admin')

@section('title', 'Vinted — Admin')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.marketplaces.index') }}">Marketplaces</a></p>
                    <h2 class="admin-list-title">Vinted</h2>
                    <p class="admin-list-lede">
                        What is sold on Vinted and nowhere else, kept by hand.
                        <strong>None of these items appears on the shop.</strong>
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.marketplaces.vinted.items.create') }}" class="btn btn-primary">Add item</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($availableCount) }} to sell</span>
                <span class="admin-list-chip admin-list-chip--shipped">{{ number_format($piecesLeft) }} {{ $piecesLeft === 1 ? 'piece' : 'pieces' }} left</span>
                <span class="admin-list-chip admin-list-chip--refunded">{{ number_format($soldOutCount) }} sold out</span>
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Vinted item tabs">
            <a href="{{ route('admin.marketplaces.vinted') }}" class="{{ $tab === 'available' ? 'active' : '' }}">
                Available <span class="admin-tab-count">{{ number_format($availableCount) }}</span>
            </a>
            <a href="{{ route('admin.marketplaces.vinted', ['tab' => 'sold-out']) }}" class="{{ $tab === 'sold-out' ? 'active' : '' }}">
                Sold out <span class="admin-tab-count">{{ number_format($soldOutCount) }}</span>
            </a>
        </nav>

        @if ($items->isEmpty())
            <p class="empty-state">
                @if ($tab === 'sold-out')
                    Nothing is sold out. An item moves here when its last piece is sold.
                @else
                    No Vinted item to sell. Start one with Add item.
                @endif
            </p>
        @else
            <div class="admin-table-wrap">
                {{-- The drafts table's look: a thumbnail, a title, figures. --}}
                <table class="admin-table vinted-drafts">
                    <thead>
                        <tr>
                            <th class="admin-table-media"></th>
                            <th>Item</th>
                            <th class="admin-table-num" title="What the whole lot cost">Purchase price</th>
                            <th class="admin-table-num" title="Purchase price divided by the quantity bought">Per piece</th>
                            <th class="admin-table-num">Quantity</th>
                            <th class="admin-table-num" title="What the sales brought in">Sold</th>
                            <th class="admin-table-num" title="Sold prices less the cost of the pieces sold">Profit</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php($edit = route('admin.marketplaces.vinted.items.edit', $item))
                            <tr>
                                <td class="admin-table-media">
                                    @if ($item->thumbnailUrl())
                                        <img src="{{ $item->thumbnailUrl() }}" alt="" width="44" height="44" class="admin-product-thumb" loading="lazy">
                                    @else
                                        <span class="admin-product-thumb vinted-draft-nophoto" aria-hidden="true"></span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ $edit }}" class="admin-table-strong admin-name-clamp">{{ $item->title }}</a>
                                    @if (filled($item->vinted_url))
                                        <a href="{{ $item->vinted_url }}" target="_blank" rel="noopener noreferrer" class="admin-table-sub vinted-item-link">View on Vinted</a>
                                    @endif
                                </td>
                                <td class="admin-table-num">{{ format_euros($item->purchase_total_cents) }}</td>
                                <td class="admin-table-num">{{ format_euros($item->unitCostCents()) }}</td>
                                <td class="admin-table-num">
                                    {{ number_format($item->quantity) }}
                                    <span class="admin-table-sub">of {{ number_format($item->lot_quantity) }} bought</span>
                                </td>
                                @if ($item->soldCount() === 0)
                                    <td class="admin-table-num"><span class="nb-none">—</span></td>
                                    <td class="admin-table-num"><span class="nb-none">—</span></td>
                                @else
                                    <td class="admin-table-num">
                                        {{ format_euros($item->soldTotalCents()) }}
                                        <span class="admin-table-sub">{{ number_format($item->soldCount()) }} sold</span>
                                    </td>
                                    <td class="admin-table-num{{ $item->profitCents() < 0 ? ' vinted-item-loss' : '' }}">{{ format_euros($item->profitCents()) }}</td>
                                @endif
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="{{ $edit }}" class="btn btn-sm btn-secondary">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
