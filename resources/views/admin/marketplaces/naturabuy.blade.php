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
                href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'missing']) }}"
                class="{{ $tab === 'missing' ? 'active' : '' }}"
            >
                Not listed <span class="admin-tab-count">{{ number_format($missingCount) }}</span>
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
            <a
                href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'price-mismatch']) }}"
                class="{{ $tab === 'price-mismatch' ? 'active' : '' }}{{ $priceMismatchCount > 0 ? ' nb-tab-attention' : '' }}"
                title="Listings whose price is not exactly the suggested price"
            >
                Price mismatch <span class="admin-tab-count">{{ number_format($priceMismatchCount) }}</span>
            </a>
            <a
                href="{{ route('admin.marketplaces.naturabuy', ['tab' => 'price-match']) }}"
                class="{{ $tab === 'price-match' ? 'active' : '' }}"
                title="Listings priced exactly at the suggested price"
            >
                Price match <span class="admin-tab-count">{{ number_format($priceMatchCount) }}</span>
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
                    <label class="admin-field-label" for="nb-search">Search</label>
                    <input id="nb-search" type="search" name="search" class="form-control admin-toolbar-search" placeholder="Title or internal code…" value="{{ $search }}">
                </div>
                @if ($tab === 'missing')
                    <div class="admin-filter-field">
                        <label class="admin-field-label" for="nb-availability">Availability</label>
                        <select id="nb-availability" name="availability" class="form-control">
                            <option value="">All</option>
                            @foreach (['in_stock' => 'In stock', 'low_stock' => 'Last pieces', 'restocking' => 'Restocking', 'at_supplier' => 'At supplier', 'out_of_stock' => 'Out of stock'] as $value => $label)
                                <option value="{{ $value }}" @selected($availability === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </div>
        </form>

        @if ($tab === 'missing')
            {{-- Cet onglet renverse la table : ce sont des produits d'ici, pas
                 des annonces de chez eux. Les colonnes changent donc aussi. --}}
            @if ($missing->isEmpty())
                <p class="empty-state">Every active product is listed on NaturaBuy.</p>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table nb-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="nb-num">Price</th>
                                <th class="nb-num">Stock</th>
                                <th class="nb-num" title="Photos on our shop: main photo plus gallery">Photos</th>
                                <th>Availability</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($missing as $product)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.products.edit', $product) }}">{{ $product->localizedName() }}</a>
                                        @if (! empty($unlistedVariants[$product->id]))
                                            {{-- Partly online: which variants still need a listing. --}}
                                            <ul class="nb-unlisted-variants" aria-label="Variants not listed yet">
                                                @foreach ($unlistedVariants[$product->id] as $variant)
                                                    <li>
                                                        <span class="nb-unlisted-arrow" aria-hidden="true">&#8627;</span>
                                                        {{ $variant->label() !== '' ? $variant->label() : 'Variant' }}
                                                        ·
                                                        @if (filled($variant->sku))
                                                            <code class="nb-code">{{ $variant->sku }}</code>
                                                        @else
                                                            <span class="nb-none">no SKU</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @include('admin.marketplaces.partials.copy-product-text', [
                                            'name' => $product->localizedName(),
                                            'description' => $product->localizedDescription(),
                                            'descriptionText' => $product->localizedDescriptionText(),
                                        ])
                                    </td>
                                    <td>
                                        @if ($product->sku)
                                            <code class="nb-code">{{ $product->sku }}</code>
                                        @elseif ($product->variants->isNotEmpty())
                                            <span class="nb-none">{{ $product->variants->count() }} variants</span>
                                        @else
                                            <span class="nb-none">—</span>
                                        @endif
                                    </td>
                                    <td class="nb-num">{{ format_euros($product->price_cents) }}</td>
                                    <td class="nb-num">{{ number_format($product->quantity) }}</td>
                                    <td class="nb-num">@include('admin.marketplaces.partials.shop-image-count', ['count' => $product->shopImageCount()])</td>
                                    <td>
                                        @php($state = $product->availabilityState())
                                        <span class="admin-availability-chip is-{{ str_replace('_', '-', $state) }}">
                                            {{ ['in_stock' => 'In stock', 'low_stock' => 'Last pieces', 'restocking' => 'Restocking', 'at_supplier' => 'At supplier', 'out_of_stock' => 'Out of stock'][$state] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('admin.partials.pager', ['paginator' => $missing])
            @endif
        @elseif ($listings->isEmpty())
            <p class="empty-state">
                No listings yet. Run <code>php artisan naturabuy:sync</code> to pull them in.
            </p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table nb-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th class="nb-num">Price</th>
                            {{-- The quantity and name mismatch tabs are about stock and wording: the money columns only crowd them. --}}
                            @php($withoutMoney = in_array($tab, ['qty-mismatch', 'name-mismatch'], true))
                            @unless ($withoutMoney)
                            <th class="nb-num" title="Price minus NaturaBuy's commission: {{ \App\Models\NaturabuyListing::COMMISSION_PERCENT_EXCL_VAT }}% plus {{ \App\Models\NaturabuyListing::COMMISSION_VAT_PERCENT }}% VAT">You get</th>
                            <th class="nb-num" title="Average purchase cost, incl. VAT">Product cost</th>
                            <th class="nb-num" title="You get, minus the product cost">Profit</th>
                            <th class="nb-num" title="The lowest price ending in ,90 that leaves at least {{ \App\Models\NaturabuyListing::TARGET_PROFIT_PERCENT }}% profit after NaturaBuy's commission and {{ format_euros(\App\Models\NaturabuyListing::PACKAGING_COST_CENTS) }} of packaging">Suggested price</th>
                            <th title="Whether the price is exactly the suggested price">At suggested</th>
                            @endunless
                            {{-- The two price tabs are about money: quantities and photos only crowd them. --}}
                            @php($pricesOnly = in_array($tab, ['price-mismatch', 'price-match'], true))
                            @unless ($pricesOnly)
                                <th class="nb-num">NB qty</th>
                                <th class="nb-num">Ours</th>
                            @endunless
                            {{-- Every row of the catalogue and price tabs is in the catalogue: the column would only repeat it. --}}
                            @if (! in_array($tab, ['in-catalogue', 'price-mismatch', 'price-match'], true))
                                <th>Catalogue</th>
                            @endif
                            @unless ($pricesOnly)
                                <th class="nb-num" title="Photos on our shop: main photo plus gallery">Photos</th>
                                <th class="nb-num" title="Photos on the NaturaBuy listing, read from its page once a week">NB photos</th>
                            @endunless
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

                                    {{-- The internal code sits under the title rather than in a column of its own. --}}
                                    @if ($listing->internalcode)
                                        <span class="nb-title-code"><code class="nb-code">{{ $listing->internalcode }}</code></span>
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

                                    @if ($matchedProductId)
                                        @include('admin.marketplaces.partials.copy-product-text', [
                                            'name' => $match['name'],
                                            'description' => $match['description'],
                                            'descriptionText' => $match['description_text'],
                                        ])
                                    @endif
                                </td>
                                <td class="nb-num">
                                    {{ format_euros($listing->price_cents) }}
                                    @if ($listing->isDiscounted())
                                        <span class="nb-oldprice">{{ format_euros($listing->oldprice_cents) }}</span>
                                    @endif
                                </td>
                                @unless ($withoutMoney)
                                {{-- The money columns wear the orders list's chips: perceived, product cost, profit. --}}
                                <td class="nb-num"><span class="admin-order-perceived">{{ format_euros($listing->netAfterCommissionCents()) }}</span></td>
                                @php($costCents = $match['cost_cents'] ?? null)
                                <td class="nb-num">
                                    @if ($costCents !== null)
                                        <span class="stripe-fee-chip" title="Average purchase cost, incl. VAT">− {{ format_euros($costCents) }}</span>
                                    @else
                                        <span class="nb-none" title="{{ $match ? 'Nothing received on a purchase order yet, so no cost to deduct' : 'Not matched to a product in the catalogue' }}">no cost</span>
                                    @endif
                                </td>
                                <td class="nb-num">
                                    @if ($costCents !== null)
                                        @php($profit = $listing->netAfterCommissionCents() - $costCents)
                                        {{-- The percentage reads as on the orders list: profit as a share of what the goods cost. --}}
                                        <span class="admin-order-profit nb-profit{{ $profit < 0 ? ' is-loss' : '' }}">{{ format_euros($profit) }}@if ($costCents > 0)<span class="admin-order-profit-pct" title="Profit as a share of what the goods cost">({{ number_format($profit / $costCents * 100, 1, ',', ' ') }} %)</span>@endif</span>
                                    @else
                                        <span class="nb-none" title="No product cost to deduct">–</span>
                                    @endif
                                </td>
                                <td class="nb-num">
                                    @if ($costCents !== null)
                                        @php($suggested = new \App\Models\NaturabuyListing(['price_cents' => \App\Models\NaturabuyListing::suggestedPriceCents($costCents)]))
                                        @php($suggestedProfit = $suggested->netAfterCommissionCents() - $costCents - \App\Models\NaturabuyListing::PACKAGING_COST_CENTS)
                                        {{-- Green when the listing already sells at or above it, amber when it sells for less. --}}
                                        @php($underpriced = $listing->price_cents < $suggested->price_cents)
                                        <span
                                            class="admin-order-profit nb-suggested {{ $underpriced ? 'is-above' : 'is-met' }}"
                                            title="{{ $underpriced ? 'The listing sells for less than this' : 'The listing already sells at or above this' }}. Pays out {{ format_euros($suggested->netAfterCommissionCents()) }}, a profit of {{ format_euros($suggestedProfit) }} after {{ format_euros(\App\Models\NaturabuyListing::PACKAGING_COST_CENTS) }} of packaging"
                                        >{{ format_euros($suggested->price_cents) }}@if ($costCents > 0)<span class="admin-order-profit-pct" title="Profit at this price, packaging paid, as a share of what the goods cost">({{ number_format($suggestedProfit / $costCents * 100, 1, ',', ' ') }} %)</span>@endif</span>
                                    @else
                                        <span class="nb-none" title="No product cost to base it on">–</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- The product list's check and cross pill: is the price exactly the suggestion? --}}
                                    @if ($costCents !== null)
                                        @php($atSuggested = $listing->price_cents === $suggested->price_cents)
                                        <span
                                            class="gtin-flag {{ $atSuggested ? 'is-set' : 'is-missing' }}"
                                            title="{{ $atSuggested ? 'Priced at the suggested price' : 'Not at the suggested price' }}"
                                        >
                                            @if ($atSuggested)
                                                <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                                    <path d="m5 13 4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            @else
                                                <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                                    <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                                </svg>
                                            @endif
                                            <span class="sr-only">{{ $atSuggested ? 'At the suggested price' : 'Not at the suggested price' }}</span>
                                        </span>
                                    @else
                                        <span class="nb-none" title="No suggested price without a product cost">–</span>
                                    @endif
                                </td>
                                @endunless
                                @unless ($pricesOnly)
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
                                @endunless
                                @if (! in_array($tab, ['in-catalogue', 'price-mismatch', 'price-match'], true))
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
                                @endif
                                @unless ($pricesOnly)
                                <td class="nb-num">@include('admin.marketplaces.partials.shop-image-count', ['count' => $match['image_count'] ?? null])</td>
                                <td class="nb-num">
                                    {{-- Read from their page by naturabuy:count-photos, one listing a minute. --}}
                                    @if ($listing->photo_count === null)
                                        <span class="nb-none" title="{{ $listing->photos_checked_at ? 'Could not be read, tried again soon' : 'Not read yet' }}">—</span>
                                    @elseif (($match['image_count'] ?? null) !== null && $match['image_count'] > $listing->photo_count)
                                        <span class="admin-availability-chip is-low-stock" title="Fewer photos than on our shop ({{ $match['image_count'] }}). Read {{ $listing->photos_checked_at?->format('d M Y') }}">{{ $listing->photo_count }}</span>
                                    @else
                                        <span title="Read {{ $listing->photos_checked_at?->format('d M Y') }}">{{ $listing->photo_count }}</span>
                                    @endif
                                </td>
                                @endunless
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

            @include('admin.partials.pager', ['paginator' => $listings])
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/admin-naturabuy-sync.js') }}" defer></script>
    <script src="{{ versioned_asset('js/admin-copy-product-text.js') }}" defer></script>
@endpush
