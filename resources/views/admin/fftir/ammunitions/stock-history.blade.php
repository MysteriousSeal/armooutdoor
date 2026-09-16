@extends('layouts.admin')

@section('title', 'Stock history, '.$ammunition->brand.' '.$ammunition->denomination)

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.ammunitions.edit', $ammunition) }}">Ammunitions</a></p>
                    <h2 class="fftir-title">{{ $ammunition->brand }} {{ $ammunition->denomination }}</h2>
                    <p class="fftir-lede">Every recorded change to this ammunition's stock.</p>
                </div>
                <div class="fftir-actions">
                    <a href="{{ route('admin.fftir.ammunitions.edit', $ammunition) }}" class="btn btn-secondary">Back to ammunition</a>
                </div>
            </div>
            <div class="fftir-meta">
                <span class="fftir-chip fftir-num">{{ number_format($ammunition->quantity) }} in stock</span>
                <span class="fftir-chip fftir-num">{{ number_format($movements->total()) }} shown</span>
            </div>
        </header>

        @if ($movements->isEmpty())
            <p class="fftir-empty">No stock movements recorded yet.</p>
        @else
            <div class="fftir-table-wrap">
                <table class="fftir-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th class="fftir-stock-cell">Change</th>
                            <th class="fftir-stock-cell">Resulting stock</th>
                            <th class="fftir-stock-cell">Price paid</th>
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
                                    <span class="fftir-sub">{{ $movement->created_at->format('H:i') }}</span>
                                </td>
                                <td class="fftir-stock-cell">
                                    <span class="fftir-stock-delta {{ $movement->delta >= 0 ? 'is-up' : 'is-down' }}">
                                        {{ $movement->delta >= 0 ? '+' : '-' }}{{ number_format(abs($movement->delta)) }}
                                    </span>
                                </td>
                                <td class="fftir-stock-cell">
                                    <span class="fftir-stock-balance">{{ number_format($movement->quantity_after) }}</span>
                                    <span class="fftir-sub">from {{ number_format($movement->quantity_before) }}</span>
                                </td>
                                <td class="fftir-stock-cell">{{ $movement->total_price_cents !== null ? format_euros($movement->total_price_cents) : 'N/A' }}</td>
                                <td>{{ $movement->user?->name ?? 'N/A' }}</td>
                                <td class="fftir-note-cell">{{ $movement->note ?: 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $movements])
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush
