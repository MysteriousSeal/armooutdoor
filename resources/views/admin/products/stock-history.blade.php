@extends('layouts.admin')

@section('title', 'Stock history — '.$product->localizedName())

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div class="stock-history-heading">
                    <img class="admin-product-thumb" src="{{ $product->thumbnailUrl() }}" alt="" loading="lazy">
                    <div>
                        <p class="admin-list-kicker">Inventory</p>
                        <h2 class="admin-list-title">{{ $product->localizedName() }}</h2>
                        <p class="admin-list-lede">Every recorded change to this product's stock.</p>
                    </div>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary">Back to product</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($product->quantity) }} in stock</span>
                @if ($product->hasVariants())
                    <span class="admin-list-chip">{{ number_format($product->variants->count()) }} variants</span>
                @endif
                <span class="admin-list-chip">{{ number_format($movements->count()) }} shown</span>
            </div>
        </header>

        {{-- Le solde enregistré à chaque ligne sert précisément à ça : si le
             dernier connu ne correspond plus au stock réel, quelque chose a
             écrit sans passer par le journal. --}}
        @if ($drift !== [])
            <div class="stock-drift" role="status">
                <p class="stock-drift-title">Something changed stock outside this log.</p>
                <ul class="stock-drift-list">
                    @foreach ($drift as $row)
                        <li>
                            <strong>{{ $row['label'] }}</strong> — the log ends at
                            {{ number_format($row['expected']) }}, the shelf says {{ number_format($row['actual']) }}.
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('admin.products.stock-history', $product) }}" class="admin-filter-bar">
            <div class="admin-filter-row">
                <div class="admin-filter-field">
                    <label class="admin-field-label" for="stock-reason">Reason</label>
                    <select id="stock-reason" name="reason" class="form-control">
                        <option value="">All reasons</option>
                        @foreach (\App\Enums\StockMovementReason::cases() as $case)
                            <option value="{{ $case->value }}" @selected($reason === $case)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($product->hasVariants())
                    <div class="admin-filter-field">
                        <label class="admin-field-label" for="stock-variant">Variant</label>
                        <select id="stock-variant" name="variant" class="form-control">
                            <option value="">All variants</option>
                            @foreach ($product->variants as $variant)
                                <option value="{{ $variant->id }}" @selected($variantId === $variant->id)>
                                    {{ $variant->label() ?: 'Variant #'.$variant->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('admin.products.stock-history', $product) }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        @if ($movements->isEmpty())
            <p class="empty-state">
                No stock movements recorded yet.
                <span class="stock-empty-note">
                    The log starts from the day it was switched on and holds nothing from before, by design.
                    The next change to this product's stock will appear here.
                </span>
            </p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            @if ($product->hasVariants())
                                <th>Variant</th>
                            @endif
                            <th>Reason</th>
                            <th class="stock-move-cell">Change</th>
                            <th class="stock-move-cell">Resulting stock</th>
                            <th>Source</th>
                            <th>Who</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $movement)
                            <tr>
                                <td>
                                    <time datetime="{{ $movement->created_at->toIso8601String() }}">
                                        {{ $movement->created_at->format('d/m/Y') }}
                                    </time>
                                    <span class="admin-table-sub">{{ $movement->created_at->format('H:i') }}</span>
                                </td>
                                @if ($product->hasVariants())
                                    <td>{{ $movement->variantLabel() ?? '—' }}</td>
                                @endif
                                <td>
                                    <span class="stock-reason is-{{ $movement->reason->direction() }}">
                                        {{ $movement->reason->label() }}
                                    </span>
                                </td>
                                <td class="stock-move-cell">
                                    <span class="stock-delta {{ $movement->delta >= 0 ? 'is-up' : 'is-down' }}">
                                        {{ $movement->delta >= 0 ? '+' : '−' }}{{ number_format(abs($movement->delta)) }}
                                    </span>
                                </td>
                                <td class="stock-move-cell">
                                    <span class="stock-balance">{{ number_format($movement->quantity_after) }}</span>
                                    <span class="admin-table-sub">from {{ number_format($movement->quantity_before) }}</span>
                                </td>
                                <td>
                                    @if ($movement->subjectUrl())
                                        <a href="{{ $movement->subjectUrl() }}" class="admin-table-strong">{{ $movement->subjectLabel() }}</a>
                                    @else
                                        {{ $movement->subjectLabel() ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $movement->user?->name ?? '—' }}</td>
                                <td class="stock-note-cell">{{ $movement->note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $movements])
        @endif
    </div>
@endsection
