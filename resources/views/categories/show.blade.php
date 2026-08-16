@extends('layouts.app')

@section('title', $category->localizedName().' — '.config('app.name'))
@section('meta_description', $category->localizedDescription())
@section('canonical', localized_route('categories.show', ['category' => $category->slug]))

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

        <header class="page-header">
            <h2 class="page-title">{{ $category->localizedName() }}</h2>
            <p class="page-lede">{{ $category->localizedDescription() }}</p>
            <p class="page-meta">
                {{ trans_choice('store.products_count', $products->count(), ['count' => $products->count()]) }}
            </p>
        </header>

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

        @if ($products->isEmpty())
            <p class="empty-state">{{ __('store.empty_category') }}</p>
        @else
            <form method="GET" class="sort-form" action="{{ localized_route('categories.show', ['category' => $category->slug]) }}">
                <label for="category-sort">{{ __('store.sort_label') }}</label>
                <div class="sort-select-wrap">
                    <select id="category-sort" name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="name" @selected($sort === 'name')>{{ __('store.sort_name') }}</option>
                        <option value="price-asc" @selected($sort === 'price-asc')>{{ __('store.sort_price_asc') }}</option>
                        <option value="price-desc" @selected($sort === 'price-desc')>{{ __('store.sort_price_desc') }}</option>
                    </select>
                </div>
                <noscript>
                    <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.sort_label') }}</button>
                </noscript>
            </form>

            <div class="product-grid">
                @foreach ($products as $index => $product)
                    @include('partials.product-card', ['product' => $product, 'lazy' => $index > 1])
                @endforeach
            </div>
        @endif
    </div>
@endsection
