@extends('layouts.admin')

@section('title', 'NaturaBuy — Admin')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.marketplaces.index') }}">Marketplaces</a></p>
                    <h2 class="admin-list-title">NaturaBuy</h2>
                    <p class="admin-list-lede">
                        Everything currently on sale on NaturaBuy, as their API reports it.
                        @if ($syncedAt)
                            Last synced {{ admin_relative_date($syncedAt) }}.
                        @endif
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    <form method="POST" action="{{ route('admin.marketplaces.naturabuy.sync') }}" class="nb-sync-form">
                        @csrf
                        <button type="submit" class="btn btn-primary nb-sync-btn" data-syncing-label="Syncing…">
                            <span class="nb-sync-icon" aria-hidden="true">&#8635;</span>
                            <span class="nb-sync-text">Resync</span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($allCount) }} on sale</span>
                <span class="admin-list-chip admin-list-chip--shipped">{{ number_format($inStockCount) }} in stock</span>
                <span class="admin-list-chip admin-list-chip--refunded">{{ number_format($outOfStockCount) }} out of stock</span>
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Listing tabs">
            <a href="{{ route('admin.marketplaces.naturabuy') }}" class="{{ $tab === 'all' ? 'active' : '' }}">
                All <span class="admin-tab-count">{{ number_format($allCount) }}</span>
            </a>
            <a href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'in-catalogue']) }}" class="starts-group {{ $tab === 'in-catalogue' ? 'active' : '' }}">
                In catalogue <span class="admin-tab-count">{{ number_format($inCatalogueCount) }}</span>
            </a>
            <a href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'not-in-catalogue']) }}" class="{{ $tab === 'not-in-catalogue' ? 'active' : '' }}">
                Not in catalogue <span class="admin-tab-count">{{ number_format($notInCatalogueCount) }}</span>
            </a>
            <a
                href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'qty-mismatch']) }}"
                class="starts-group {{ $tab === 'qty-mismatch' ? 'active' : '' }}{{ $mismatchCount > 0 ? ' nb-tab-attention' : '' }}"
            >
                Qty mismatch <span class="admin-tab-count">{{ number_format($mismatchCount) }}</span>
            </a>
            <a
                href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'name-mismatch']) }}"
                class="{{ $tab === 'name-mismatch' ? 'active' : '' }}{{ $nameMismatchCount > 0 ? ' nb-tab-attention' : '' }}"
            >
                Name mismatch <span class="admin-tab-count">{{ number_format($nameMismatchCount) }}</span>
            </a>
            <a href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'in-stock']) }}" class="starts-group {{ $tab === 'in-stock' ? 'active' : '' }}">
                In stock <span class="admin-tab-count">{{ number_format($inStockCount) }}</span>
            </a>
            <a
                href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'out-of-stock']) }}"
                class="{{ $tab === 'out-of-stock' ? 'active' : '' }}{{ $outOfStockCount > 0 ? ' nb-tab-attention' : '' }}"
            >
                Out of stock <span class="admin-tab-count">{{ number_format($outOfStockCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.marketplaces.naturabuy') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-filter-label" for="nb-search">Search</label>
                    <input id="nb-search" type="search" name="search" class="form-control admin-toolbar-search" placeholder="Title or internal code…" value="{{ $search }}">
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </div>
        </form>

        @if ($listings->isEmpty())
            <p class="empty-state">
                No listings yet. Run <code>php artisan naturabuy:sync</code> to pull them in.
            </p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table nb-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Internal code</th>
                            <th class="nb-num">Price</th>
                            <th class="nb-num">NB qty</th>
                            <th class="nb-num">Ours</th>
                            <th>Catalogue</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listings as $listing)
                            <tr>
                                @php($match = $listing->internalcode ? ($catalogueMatches[$listing->internalcode] ?? null) : null)
                                @php($matchedProductId = $match['product_id'] ?? null)
                                @php($differs = $match !== null && $match['quantity'] !== $listing->quantity)
                                <td>
                                    @if ($listing->publicUrl())
                                        <a href="{{ $listing->publicUrl() }}" target="_blank" rel="noopener noreferrer">{{ $listing->title }}</a>
                                    @else
                                        {{ $listing->title }}
                                    @endif

                                    {{-- Le nom d'ici seulement sur l'onglet du catalogue :
                                         ailleurs il doublerait chaque ligne sans servir. --}}
                                    @if (in_array($tab, ['in-catalogue', 'name-mismatch'], true) && $matchedProductId)
                                        @php($nameDiffers = ($match['name'] ?? '') !== $listing->title)
                                        <span class="nb-ourname{{ $nameDiffers ? ' is-differing' : '' }}">
                                            <span class="nb-ourname-arrow" aria-hidden="true">&#8627;</span>
                                            <a href="{{ route('admin.products.edit', $matchedProductId) }}">{{ $match['name'] }}</a>
                                            @if ($nameDiffers)
                                                <span class="nb-ourname-tag" title="This product is named differently here and on NaturaBuy">differs</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($listing->internalcode)
                                        <code class="nb-code">{{ $listing->internalcode }}</code>
                                    @else
                                        <span class="nb-none">—</span>
                                    @endif
                                </td>
                                <td class="nb-num">
                                    {{ format_euros($listing->price_cents) }}
                                    @if ($listing->isDiscounted())
                                        <span class="nb-oldprice">{{ format_euros($listing->oldprice_cents) }}</span>
                                    @endif
                                </td>
                                <td class="nb-num">{{ number_format($listing->quantity) }}</td>
                                <td class="nb-num">
                                    @if ($match)
                                        <span class="nb-ours{{ $differs ? ' is-differing' : '' }}">
                                            {{ number_format($match['quantity']) }}
                                            @if ($differs)
                                                <span class="nb-delta" title="NaturaBuy {{ $listing->quantity }} vs catalogue {{ $match['quantity'] }}">
                                                    {{ $match['quantity'] > $listing->quantity ? '+' : '−' }}{{ number_format(abs($match['quantity'] - $listing->quantity)) }}
                                                </span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="nb-none">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($matchedProductId)
                                        {{-- Un rapprochement par préfixe est une déduction, pas une
                                             certitude : il se distingue de la correspondance exacte. --}}
                                        <a
                                            href="{{ route('admin.products.edit', $matchedProductId) }}"
                                            class="admin-availability-chip nb-match {{ $match['exact'] ? 'is-in-stock' : 'is-at-supplier nb-match--prefix' }}"
                                            title="{{ $match['exact']
                                                ? 'Exact SKU match. Open this product'
                                                : 'Matched on the code prefix: our variant SKUs start with '.$listing->internalcode.'-. Open this product' }}"
                                        >{{ $match['exact'] ? 'In catalogue' : 'By prefix' }}</a>
                                    @elseif ($listing->internalcode)
                                        {{-- Code renseigné chez eux, mais rien ici : c'est un écart
                                             à corriger, pas une donnée manquante. --}}
                                        <span class="admin-availability-chip is-out-of-stock">Not in catalogue</span>
                                    @else
                                        <span class="admin-availability-chip nb-nocode">No code</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($listing->out_of_stock)
                                        <span class="order-chip order-chip--refunded">Out of stock</span>
                                    @else
                                        <span class="order-chip order-chip--shipped">In stock</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.pager', ['paginator' => $listings])
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-naturabuy-sync.js') }}" defer></script>
@endpush
