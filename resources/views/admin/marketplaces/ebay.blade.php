@extends('layouts.admin')

@section('title', 'eBay · Admin')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.marketplaces.index') }}">Marketplaces</a></p>
                    <h2 class="admin-list-title">eBay</h2>
                    <p class="admin-list-lede">
                        The active listings on eBay.fr, as the seller account reports them.
                        @if ($syncedAt)
                            Last synced {{ admin_relative_date($syncedAt) }}.
                        @endif
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    @if ($connection && ! $connection->hasExpired())
                        <form method="POST" action="{{ route('admin.marketplaces.ebay.sync') }}" class="nb-sync-form">
                            @csrf
                            <button type="submit" class="btn btn-primary nb-sync-btn" data-syncing-label="Syncing…">
                                <span class="nb-sync-icon" aria-hidden="true">&#8635;</span>
                                <span class="nb-sync-text">Sync</span>
                            </button>
                        </form>
                        {{-- Also run every fifteen minutes by ebay:sync-stock. --}}
                        <form method="POST" action="{{ route('admin.marketplaces.ebay.stock') }}" onsubmit="return confirm('Send the shop stock to every eBay listing that differs?')">
                            @csrf
                            <button type="submit" class="btn btn-secondary" title="Read eBay, then send our quantity to each listing that differs. Runs every 15 minutes on its own.">Push stock</button>
                        </form>
                        <form method="POST" action="{{ route('admin.marketplaces.ebay.disconnect') }}" onsubmit="return confirm('Disconnect eBay?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary">Disconnect</button>
                        </form>
                    @elseif ($configured)
                        <form method="POST" action="{{ route('admin.marketplaces.ebay.connect') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ $connection ? 'Reconnect eBay' : 'Connect eBay' }}</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="admin-list-meta">
                @if ($connection && ! $connection->hasExpired())
                    <span class="admin-list-chip admin-list-chip--shipped">Connected</span>
                    <span class="admin-list-chip" title="eBay asks for a new sign-in after this date">Sign-in valid until {{ $connection->refresh_token_expires_at?->format('d M Y') }}</span>
                @elseif ($connection)
                    <span class="admin-list-chip admin-list-chip--refunded">Sign-in expired</span>
                @else
                    <span class="admin-list-chip">Not connected</span>
                @endif
                <span class="admin-list-chip">{{ number_format($count) }} {{ $count === 1 ? 'listing' : 'listings' }}</span>
            </div>
        </header>

        @unless ($configured)
            <p class="empty-state">
                No eBay app is set up. Add <code>EBAY_CLIENT_ID</code>, <code>EBAY_CLIENT_SECRET</code> and
                <code>EBAY_RU_NAME</code> to <code>.env</code>, then come back here to connect the seller account.
            </p>
        @endunless

        <form method="GET" action="{{ route('admin.marketplaces.ebay') }}" class="admin-filter-bar">
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-field-label" for="ebay-search">Search</label>
                    <input id="ebay-search" type="search" name="search" class="form-control admin-toolbar-search" placeholder="Title or SKU…" value="{{ $search }}">
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </div>
        </form>

        @if ($listings->isEmpty())
            <p class="empty-state">
                @if ($search !== '')
                    No listing matches this search.
                @else
                    No listings yet. Connect eBay and sync to pull them in.
                @endif
            </p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table nb-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th class="nb-num">Price</th>
                            <th class="nb-num">eBay qty</th>
                            <th class="nb-num">Ours</th>
                            <th class="nb-num">Sold</th>
                            <th>Catalogue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listings as $listing)
                            {{-- A listing with variations is matched on each of
                                 their SKUs; ours is the sum over those found. --}}
                            @php($matches = collect($listing->skus())->map(fn ($sku) => $catalogue[$sku] ?? null)->filter())
                            @php($ours = $matches->isEmpty() ? null : $matches->sum('quantity'))
                            <tr>
                                <td>
                                    @if ($listing->url)
                                        <a href="{{ $listing->url }}" target="_blank" rel="noopener noreferrer">{{ $listing->title }}</a>
                                    @else
                                        {{ $listing->title }}
                                    @endif
                                    @if ($listing->sku)
                                        <span class="nb-title-code"><code class="nb-code">{{ $listing->sku }}</code></span>
                                    @endif
                                    @if ($listing->variations)
                                        <span class="nb-title-code">{{ count($listing->variations) }} variations</span>
                                    @endif
                                </td>
                                <td class="nb-num">{{ format_euros($listing->price_cents) }}</td>
                                <td class="nb-num">{{ $listing->quantity }}</td>
                                <td class="nb-num">
                                    @if ($ours === null)
                                        <span class="nb-none">-</span>
                                    @elseif ($ours !== $listing->quantity)
                                        <span class="admin-availability-chip is-low-stock" title="Not the quantity on eBay: the next stock push sends this one">{{ $ours }}</span>
                                    @else
                                        {{ $ours }}
                                    @endif
                                </td>
                                <td class="nb-num">{{ $listing->quantity_sold }}</td>
                                <td>
                                    @if ($matches->isNotEmpty())
                                        @php($product = $matches->first()['product'])
                                        <a href="{{ route('admin.products.edit', $product) }}" class="admin-availability-chip is-in-stock">{{ $product->localizedName() }}</a>
                                    @elseif ($listing->skus() !== [])
                                        <span class="admin-availability-chip is-out-of-stock">Not in catalogue</span>
                                    @else
                                        <span class="admin-availability-chip nb-nocode">No SKU</span>
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
