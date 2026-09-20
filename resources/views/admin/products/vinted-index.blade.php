@extends('layouts.admin')

@section('title', 'Vinted listings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">
                        <a href="{{ route('admin.products.edit', $product) }}">{{ $product->localizedName() }}</a>
                    </p>
                    <h2 class="admin-list-title">Vinted listings</h2>
                    <p class="admin-list-lede">
                        The same article can be posted more than once, with other wording or other photos.
                        Each draft is written and kept on its own.
                        <strong>Nothing is sent from here.</strong>
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary">Back to product</a>
                    <form method="POST" action="{{ route('admin.products.vinted.store', $product) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">New listing</button>
                    </form>
                </div>
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Listing tabs">
            <a href="{{ route('admin.products.vinted.index', $product) }}" class="{{ $tab === 'active' ? 'active' : '' }}">
                Active <span class="admin-tab-count">{{ number_format($activeCount) }}</span>
            </a>
            <a href="{{ route('admin.products.vinted.index', ['product' => $product, 'tab' => 'archived']) }}" class="{{ $tab === 'archived' ? 'active' : '' }}">
                Archived <span class="admin-tab-count">{{ number_format($archivedCount) }}</span>
            </a>
        </nav>

        @if ($listings->isEmpty())
            <p class="empty-state">
                @if ($tab === 'archived')
                    No archived listing. Archive one from the Active tab when it has run its course.
                @else
                    No active Vinted listing for this product. Start one with New listing.
                @endif
            </p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table vinted-drafts">
                    <thead>
                        <tr>
                            <th class="admin-table-id">Id</th>
                            <th class="admin-table-media"></th>
                            <th>Listing</th>
                            <th class="admin-table-num">Price</th>
                            <th>Progress</th>
                            <th>{{ $tab === 'archived' ? 'Archived' : 'Saved' }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listings as $listing)
                            @php
                                $edit = route('admin.products.vinted.edit', [$product, $listing]);
                                $checks = $listing->readiness();
                                $photo = $listing->images->first();
                                $opening = trim(preg_replace('/\s+/u', ' ', (string) $listing->description));
                            @endphp
                            <tr>
                                <td class="admin-table-id"><span class="vinted-draft-id">#{{ $listing->id }}</span></td>
                                <td class="admin-table-media">
                                    @if ($photo)
                                        <img src="{{ $photo->thumbnailUrl() }}" alt="" width="44" height="44" class="admin-product-thumb" loading="lazy">
                                    @else
                                        <span class="admin-product-thumb vinted-draft-nophoto" aria-hidden="true"></span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ $edit }}" class="admin-table-strong admin-name-clamp">
                                        {{ filled($listing->title) ? $listing->title : 'Untitled draft' }}
                                    </a>
                                    @if ($opening !== '')
                                        <span class="admin-table-sub admin-name-clamp">{{ \Illuminate\Support\Str::limit($opening, 110) }}</span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    @if ($listing->price_cents === null)
                                        <span class="nb-none">—</span>
                                    @else
                                        {{ format_euros($listing->price_cents) }}
                                    @endif
                                </td>
                                <td>
                                    <span class="vinted-checks">
                                        @foreach ($checks as $label => $done)
                                            <span
                                                class="vinted-check {{ $done ? 'is-done' : '' }}"
                                                title="{{ $label }}{{ $label === 'Photos' ? ' — '.$listing->imageCount().' of '.\App\Models\VintedListing::MINIMUM_IMAGES.' needed' : '' }}{{ $done ? ' — done' : ' — still to write' }}"
                                            >
                                                <span class="vinted-check-mark" aria-hidden="true"></span>
                                                <span class="vinted-check-label">{{ $label }}</span>
                                            </span>
                                        @endforeach
                                    </span>
                                </td>
                                <td class="vinted-draft-saved">{{ ($tab === 'archived' ? $listing->archived_at : $listing->updated_at)->locale('en')->diffForHumans() }}</td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="{{ $edit }}" class="btn btn-sm btn-primary">Open</a>
                                        <form
                                            method="POST"
                                            action="{{ route($tab === 'archived' ? 'admin.products.vinted.restore' : 'admin.products.vinted.archive', [$product, $listing]) }}"
                                        >
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-secondary">{{ $tab === 'archived' ? 'Restore' : 'Archive' }}</button>
                                        </form>
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
