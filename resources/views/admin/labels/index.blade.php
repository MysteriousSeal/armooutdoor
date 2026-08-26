@extends('layouts.admin')

@section('title', 'Labels')

@section('content')
    {{--
        Every article that could wear a label, one row per printed sheet.

        A product without variants is one row; a product with them contributes
        one row per size, since each carries its own reference and barcode.
        Articles that cannot be printed yet are listed too, saying what they
        are short of: this page is also the list of what needs filling in.
    --}}
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Catalog</p>
            <h2 class="admin-list-title">Labels</h2>
            <p class="admin-list-lede">
                One sheet per article. A label needs a title, a subtitle, a reference and a barcode — the wording is
                set on the product, under Label.
            </p>
        </header>

        <nav class="admin-tabs" aria-label="Label readiness">
            @php($tabQuery = fn (string $value) => array_filter(['tab' => $value === 'all' ? null : $value, 'search' => $search ?: null]))
            <a href="{{ route('admin.labels.index', $tabQuery('all')) }}" class="{{ $tab === 'all' ? 'active' : '' }}">
                All
            </a>
            <a href="{{ route('admin.labels.index', $tabQuery('ready')) }}" class="{{ $tab === 'ready' ? 'active' : '' }}">
                Ready
                @if ($readyCount > 0)
                    <span class="admin-tab-count">{{ $readyCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.labels.index', $tabQuery('incomplete')) }}" class="label-tab-attention {{ $tab === 'incomplete' ? 'active' : '' }}">
                Incomplete
                @if ($incompleteCount > 0)
                    <span class="admin-tab-count">{{ $incompleteCount }}</span>
                @endif
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.labels.index') }}" class="admin-filter-bar">
            @if ($tab !== 'all')
                <input type="hidden" name="tab" value="{{ $tab }}">
            @endif
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-filter-label" for="label-search">Search</label>
                    <input
                        id="label-search"
                        type="search"
                        name="search"
                        class="form-control admin-toolbar-search"
                        placeholder="Name, reference or label title…"
                        value="{{ $search }}"
                    >
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-secondary">Search</button>
                    @if ($search !== '')
                        <a href="{{ route('admin.labels.index', array_filter(['tab' => $tab === 'all' ? null : $tab])) }}" class="admin-link">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($articles->isEmpty())
            <p class="empty-state">No article to show here.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th>GTIN</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($articles as $article)
                            @php($product = $article['product'])
                            <tr>
                                <td>
                                    <a href="{{ route('admin.products.edit', $product) }}">
                                        <img class="admin-product-thumb" src="{{ $article['variant']?->imageUrl() ?: $product->imageUrl() }}" alt="" loading="lazy">
                                    </a>
                                </td>
                                <td>
                                    {{-- Two lines then the ellipsis, cut by the browser
                                         rather than by the server: the whole name stays
                                         in the markup and in the tooltip. --}}
                                    @php($name = $product->name['fr'] ?? $product->localizedName())
                                    <a href="{{ route('admin.products.edit', $product) }}" class="admin-table-strong admin-name-clamp" title="{{ $name }}">{{ $name }}</a>
                                </td>
                                <td>
                                    {{-- The size is what tells two rows of the same product apart. --}}
                                    @if ($article['name'])
                                        {{ $article['name'] }}
                                    @else
                                        <span class="label-missing">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (filled($article['sku']))
                                        {{-- The reference is what gets typed into a
                                             supplier's form or a marketplace, so it
                                             copies on a click, as it does on an
                                             order's lines. --}}
                                        <button
                                            type="button"
                                            class="order-item-sku-copy admin-table-code"
                                            data-copy-code="{{ $article['sku'] }}"
                                            title="Copy this SKU"
                                            aria-label="Copy SKU {{ $article['sku'] }}"
                                        >
                                            <span class="order-item-sku-value">{{ $article['sku'] }}</span>
                                            <svg class="order-item-sku-icon" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
                                                <rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                                <path d="M5 15V5a2 2 0 0 1 2-2h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                    @else
                                        <span class="label-missing">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (filled($article['gtin']))
                                        <span class="admin-table-code">{{ $article['gtin'] }}</span>
                                    @else
                                        <span class="label-missing">—</span>
                                    @endif
                                </td>
                                <td class="label-action">
                                    @if ($article['missing'] === [])
                                        <a href="{{ $article['url'] }}" class="btn btn-secondary btn-small">Download label</a>
                                    @else
                                        {{-- Switched off rather than hidden: the fields
                                             that would switch it on are on the line
                                             below, so there is nothing to explain. --}}
                                        <span class="btn btn-secondary btn-small is-disabled" aria-disabled="true">Download label</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($article['editable'])
                                {{-- The wording, editable where it is read. One line,
                                     one Save: the list is worked through a row at a
                                     time. --}}
                                <tr class="label-form-row">
                                    <td colspan="6">
                                        <form method="POST" action="{{ route('admin.labels.update', $product) }}" class="label-form">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="back" value="{{ request()->fullUrl() }}">

                                            <label class="sr-only" for="label-title-{{ $product->id }}">Label title</label>
                                            <input type="text" id="label-title-{{ $product->id }}" name="label_title" class="form-control" value="{{ $product->label?->title }}" maxlength="120" placeholder="Title">

                                            <label class="sr-only" for="label-subtitle-{{ $product->id }}">Label subtitle</label>
                                            <input type="text" id="label-subtitle-{{ $product->id }}" name="label_subtitle" class="form-control" value="{{ $product->label?->subtitle }}" maxlength="120" placeholder="Subtitle">

                                            <label class="sr-only" for="label-composition-{{ $product->id }}">Composition</label>
                                            <input type="text" id="label-composition-{{ $product->id }}" name="label_composition" class="form-control" value="{{ $product->label?->composition }}" maxlength="500" placeholder="Composition (optional)">

                                            <label class="sr-only" for="label-mention-{{ $product->id }}">Mention</label>
                                            <input type="text" id="label-mention-{{ $product->id }}" name="label_mention" class="form-control" value="{{ $product->label?->mention }}" maxlength="500" placeholder="Mention (optional)">

                                            <button type="submit" class="btn btn-secondary btn-small">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @elseif ($article['name'])
                                {{-- The other sizes of one product share its wording,
                                     so they show none: two forms on the same fields
                                     would overwrite each other. --}}
                                <tr class="label-form-row">
                                    <td colspan="6"><span class="label-shared">Wording shared with the sizes above.</span></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $products])
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-copy-code.js') }}" defer></script>
@endpush
