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
                {{-- Cdiscount has a page of its own too, though nothing is synced:
                     it holds Octopia's templates and the export made from them. --}}
                @php($octopia = $marketplace->name === 'CDiscount')
                @php($link = $connected || $octopia)
                <{{ $link ? 'a' : 'div' }}
                    @if ($connected) href="{{ route('admin.marketplaces.naturabuy') }}" @endif
                    @if ($octopia) href="{{ route('admin.marketplaces.cdiscount') }}" @endif
                    class="marketplace-card{{ $link ? ' is-connected' : '' }}"
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
                                Synced {{ admin_relative_date($naturabuySyncedAt) }}
                            @else
                                Never synced
                            @endif
                        </span>
                    @elseif ($octopia)
                        <span class="marketplace-card-figure">{{ number_format($octopiaTemplates) }}</span>
                        <span class="marketplace-card-label">{{ $octopiaTemplates === 1 ? 'template' : 'templates' }}</span>
                        <span class="marketplace-card-foot">Filled by hand, through Octopia</span>
                    @else
                        <span class="marketplace-card-figure marketplace-card-figure--muted">—</span>
                        <span class="marketplace-card-label">not connected</span>
                        <span class="marketplace-card-foot">No API set up yet</span>
                    @endif
                </{{ $link ? 'a' : 'div' }}>
            @endforeach
        </div>
    </div>
@endsection
