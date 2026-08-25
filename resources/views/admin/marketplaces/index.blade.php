@extends('layouts.admin')

@section('title', 'Marketplaces — Admin')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Channels</p>
                    <h2 class="admin-list-title">Marketplaces</h2>
                    <p class="admin-list-lede">Where the shop sells beyond its own storefront.</p>
                </div>
            </div>
        </header>

        <div class="marketplace-grid">
            @foreach ($marketplaces as $marketplace)
                @php($connected = $marketplace->name === 'NaturaBuy')
                <{{ $connected ? 'a' : 'div' }}
                    @if ($connected) href="{{ route('admin.marketplaces.naturabuy') }}" @endif
                    class="marketplace-card{{ $connected ? ' is-connected' : '' }}"
                >
                    <span class="marketplace-card-head">
                        @if ($marketplace->logo)
                            <img src="{{ asset('images/'.$marketplace->logo) }}" alt="" class="marketplace-card-logo">
                        @else
                            <span class="marketplace-card-logo marketplace-card-logo--empty" aria-hidden="true">
                                {{ mb_substr($marketplace->name, 0, 1) }}
                            </span>
                        @endif
                        <span class="marketplace-card-name">{{ $marketplace->name }}</span>
                    </span>

                    @if ($connected)
                        <span class="marketplace-card-figure">{{ number_format($naturabuyCount) }}</span>
                        <span class="marketplace-card-label">listings</span>
                        <span class="marketplace-card-foot">
                            @if ($naturabuySyncedAt)
                                Synced {{ \Illuminate\Support\Carbon::parse($naturabuySyncedAt)->diffForHumans() }}
                            @else
                                Never synced
                            @endif
                        </span>
                    @else
                        <span class="marketplace-card-figure marketplace-card-figure--muted">—</span>
                        <span class="marketplace-card-label">not connected</span>
                        <span class="marketplace-card-foot">No API set up yet</span>
                    @endif
                </{{ $connected ? 'a' : 'div' }}>
            @endforeach
        </div>
    </div>
@endsection
