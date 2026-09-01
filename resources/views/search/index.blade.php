@extends('layouts.app')

@section('title', __('store.search_results_title', ['query' => $query]).' — '.config('app.name'))

{{-- A results page is assembled per visitor and worth nothing to anybody
     arriving cold: « cible » and « cibles » are two addresses holding the
     same products, and a search engine can invent as many as it likes. It
     stays crawlable, so the products it links to are still reached — it just
     does not become a page of its own in the index. Not disallowed in
     robots.txt either: a page that cannot be fetched cannot be read saying
     this. --}}
@section('robots', 'noindex, follow')

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.search_label') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">
                @if ($query !== '')
                    {{ __('store.search_results_title', ['query' => $query]) }}
                @else
                    {{ __('store.search_label') }}
                @endif
            </h2>
            @if ($query !== '')
                <p class="page-meta">
                    {{ trans_choice('store.products_count', $products->count(), ['count' => $products->count()]) }}
                </p>
            @endif
        </header>

        @if ($query === '')
            <p class="empty-state">{{ __('store.search_prompt') }}</p>
        @elseif ($products->isEmpty())
            <p class="empty-state">{{ __('store.search_empty') }}</p>
        @else
            <div class="product-grid">
                @foreach ($products as $index => $product)
                    @include('partials.product-card', ['product' => $product, 'lazy' => $index > 1])
                @endforeach
            </div>
        @endif
    </div>
@endsection
