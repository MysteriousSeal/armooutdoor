@extends('layouts.app')

@section('title', paginated_title($category->localizedName(), $products).' — '.config('app.name'))
@section('meta_description', $category->localizedDescription())
@section('canonical', paginated_canonical(localized_route('categories.show', ['category' => $category->slug]), $products))
@section('og_image', $category->imageUrl() ?? '')
@section('og_image_alt', $category->localizedName())

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => collect([
                ['name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                $category->parent ? [
                    'name' => $category->parent->localizedName(),
                    'item' => localized_route('categories.show', ['category' => $category->parent->slug]),
                ] : null,
                [
                    'name' => $category->localizedName(),
                    'item' => localized_route('categories.show', ['category' => $category->slug]),
                ],
            ])->filter()->values()->map(fn (array $crumb, int $index): array => [
                '@@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['item'],
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {{-- The products of the page being read, not of the whole category:
             a list that named products the visitor cannot see here would
             describe a page that does not exist. Positions carry on across
             pages so the twenty-first product is twenty-first. --}}
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => $category->localizedName(),
            'description' => $category->localizedDescription(),
            'url' => paginated_canonical(localized_route('categories.show', ['category' => $category->slug]), $products),
            'inLanguage' => 'fr-FR',
            'isPartOf' => ['@@id' => \App\Support\OrganizationSchema::websiteId()],
            'mainEntity' => [
                '@@type' => 'ItemList',
                'name' => $category->localizedName(),
                'numberOfItems' => $products->total(),
                'itemListElement' => $products->values()->map(fn ($product, $index): array => [
                    '@@type' => 'ListItem',
                    'position' => $products->firstItem() + $index,
                    'name' => $product->localizedName(),
                    'url' => localized_route('products.show', ['product' => $product->slug]),
                ])->all(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            @if ($category->parent)
                <span class="breadcrumbs-sep" aria-hidden="true">/</span>
                <a href="{{ localized_route('categories.show', ['category' => $category->parent->slug]) }}">
                    {{ $category->parent->localizedName() }}
                </a>
            @endif
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ $category->localizedName() }}</span>
        </nav>

        @include('partials.page-hero', [
            'kicker' => $category->isRoot() ? __('store.hero_kicker') : $category->parent->localizedName(),
            'title' => $category->localizedName(),
            'description' => $category->localizedDescription(),
            'tags' => [trans_choice('store.products_count', $products->total(), ['count' => $products->total()])],
            'imageUrl' => $category->imageUrl(),
        ])

        @php
            $showSidebar = ! empty($filterGroups) && $category->children->isEmpty();
            $categoryUrl = localized_route('categories.show', ['category' => $category->slug]);
            $showSort = $products->isNotEmpty() || $showSidebar;
            $hasSubcats = $category->children->isNotEmpty() || $category->parent;
        @endphp

        @if ($hasSubcats || $showSort)
            <div class="category-toolbar">
                @if ($category->children->isNotEmpty())
                    <nav class="subcat-nav" aria-label="{{ __('store.shop_subcategories') }}">
                        <a
                            href="{{ localized_route('categories.show', ['category' => $category->slug, 'sort' => $sort]) }}"
                            class="subcat-chip is-active"
                        >
                            {{ __('store.all_products') }}
                        </a>
                        @foreach ($category->children as $child)
                            @continue($child->products->isEmpty())
                            <a
                                href="{{ localized_route('categories.show', ['category' => $child->slug, 'sort' => $sort]) }}"
                                class="subcat-chip"
                            >
                                {{ $child->localizedName() }}
                                <span>{{ $child->products->count() }}</span>
                            </a>
                        @endforeach
                    </nav>
                @elseif ($category->parent)
                    <nav class="subcat-nav" aria-label="{{ __('store.shop_subcategories') }}">
                        <a
                            href="{{ localized_route('categories.show', ['category' => $category->parent->slug, 'sort' => $sort]) }}"
                            class="subcat-chip"
                        >
                            {{ __('store.all_products') }}
                        </a>
                        @foreach ($category->parent->children as $sibling)
                            @continue($sibling->products->isEmpty() && ! $sibling->is($category))
                            <a
                                href="{{ localized_route('categories.show', ['category' => $sibling->slug, 'sort' => $sort]) }}"
                                class="subcat-chip {{ $sibling->is($category) ? 'is-active' : '' }}"
                            >
                                {{ $sibling->localizedName() }}
                                <span>{{ $sibling->products->count() }}</span>
                            </a>
                        @endforeach
                    </nav>
                @endif

                @if ($showSort)
                    <form method="GET" class="sort-form" action="{{ $categoryUrl }}">
                        @foreach ($selectedFilters as $label => $value)
                            <input type="hidden" name="filter[{{ $label }}]" value="{{ $value }}">
                        @endforeach
                        <div class="sort-field">
                            <label for="category-sort">{{ __('store.sort_label') }}</label>
                            <div class="sort-select-wrap">
                                <select id="category-sort" name="sort" class="sort-select" onchange="this.form.submit()">
                                    <option value="relevance" @selected($sort === 'relevance')>{{ __('store.sort_relevance') }}</option>
                                    <option value="name" @selected($sort === 'name')>{{ __('store.sort_name') }}</option>
                                    <option value="price-asc" @selected($sort === 'price-asc')>{{ __('store.sort_price_asc') }}</option>
                                    <option value="price-desc" @selected($sort === 'price-desc')>{{ __('store.sort_price_desc') }}</option>
                                    <option value="newest" @selected($sort === 'newest')>{{ __('store.sort_newest') }}</option>
                                </select>
                            </div>
                        </div>
                        <noscript>
                            <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.sort_label') }}</button>
                        </noscript>
                    </form>
                @endif
            </div>
        @endif

        @if ($products->isEmpty() && ! $showSidebar)
            <p class="empty-state">{{ __('store.empty_category') }}</p>
        @else
            <div class="{{ $showSidebar ? 'category-layout' : '' }}">
                @if ($showSidebar)
                    <aside class="category-sidebar" aria-labelledby="category-filters-title" data-filters>
                        {{-- On mobile the whole block folds behind this button; without
                             JavaScript it never shows and everything stays open. --}}
                        <button
                            type="button"
                            class="category-filters-toggle"
                            data-filters-toggle
                            aria-expanded="false"
                            aria-controls="category-filters-form"
                        >
                            {{ __('store.filters_title') }}
                            @if (! empty($selectedFilters))
                                <span class="category-filters-toggle-count">{{ count($selectedFilters) }}</span>
                            @endif
                            <span class="category-filters-toggle-chevron" aria-hidden="true"></span>
                        </button>
                        <form method="GET" action="{{ $categoryUrl }}" class="category-sidebar-form" id="category-filters-form">
                            @if ($sort !== 'relevance')
                                <input type="hidden" name="sort" value="{{ $sort }}">
                            @endif
                            <div class="category-sidebar-head">
                                <h2 class="category-sidebar-title" id="category-filters-title">{{ __('store.filters_title') }}</h2>
                                @if (! empty($selectedFilters))
                                    <a href="{{ localized_route('categories.show', ['category' => $category->slug, 'sort' => $sort]) }}" class="category-filters-reset">
                                        {{ __('store.filter_reset') }}
                                    </a>
                                @endif
                            </div>

                            @foreach ($filterGroups as $label => $values)
                                @php
                                    // A group whose filter is applied opens by default:
                                    // the active choice must never be invisible.
                                    $activeValue = $selectedFilters[$label] ?? null;
                                @endphp
                                <fieldset class="category-filter-group {{ $activeValue !== null ? 'is-open' : '' }}">
                                    <legend class="category-filter-legend">
                                        <button
                                            type="button"
                                            class="category-filter-group-toggle"
                                            data-filter-group-toggle
                                            aria-expanded="{{ $activeValue !== null ? 'true' : 'false' }}"
                                        >
                                            <span class="category-filter-group-name">{{ $label }}</span>
                                            @if ($activeValue !== null)
                                                <span class="category-filter-group-active">{{ $activeValue }}</span>
                                            @endif
                                            <span class="category-filter-group-chevron" aria-hidden="true"></span>
                                        </button>
                                    </legend>
                                    <div class="category-filter-options">
                                        <label class="category-filter-option">
                                            <input
                                                type="radio"
                                                name="filter[{{ $label }}]"
                                                value=""
                                                @checked($activeValue === null)
                                                onchange="this.form.submit()"
                                            >
                                            <span>{{ __('store.filter_all') }}</span>
                                        </label>
                                        @foreach ($values as $option)
                                            <label class="category-filter-option">
                                                <input
                                                    type="radio"
                                                    name="filter[{{ $label }}]"
                                                    value="{{ $option['value'] }}"
                                                    @checked($activeValue === $option['value'])
                                                    onchange="this.form.submit()"
                                                >
                                                <span>{{ $option['value'] }}</span>
                                                <span class="category-filter-count">{{ $option['count'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </form>
                    </aside>
                @endif

                <div class="category-main">
                    @if ($products->isEmpty())
                        <p class="empty-state">{{ __('store.empty_category') }}</p>
                    @else
                        <div class="product-grid">
                            @foreach ($products as $index => $product)
                                @include('partials.product-card', ['product' => $product, 'lazy' => $index > 1])
                            @endforeach
                        </div>

                        @include('partials.pager', ['paginator' => $products])
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/category-filters.js') }}" defer></script>
@endpush
