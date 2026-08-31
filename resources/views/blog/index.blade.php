@extends('layouts.app')

@section('title', paginated_title(($activeCategory ? $activeCategory->localizedName().' — ' : '').__('store.blog_title'), $posts).' — '.config('app.name'))
@section('meta_description', $activeCategory?->localizedDescription() ?: __('store.blog_intro'))
@section('canonical', paginated_canonical($activeCategory ? route('blog.category', $activeCategory->slug) : route('blog.index'), $posts))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/blog.css') }}">
@endpush

@section('content')
    @php
        $listingCount = $activeCategory ? (int) $activeCategory->posts_count : $posts->total();
    @endphp
    <div class="container blog-index">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            @if ($activeCategory)
                <a href="{{ route('blog.index') }}">{{ __('store.blog_title') }}</a>
                <span class="breadcrumbs-sep" aria-hidden="true">/</span>
                <span>{{ $activeCategory->localizedName() }}</span>
            @else
                <span>{{ __('store.blog_title') }}</span>
            @endif
        </nav>

        @include('partials.page-hero', [
            {{-- Filtré, on est dans une rubrique du blog et le surtitre le dit.
                 Sans filtre, la page reste une entrée de la boutique. --}}
            'kicker' => $activeCategory ? __('store.nav_blog') : __('store.hero_kicker'),
            'title' => $activeCategory?->localizedName() ?? __('store.blog_title'),
            'description' => $activeCategory?->localizedDescription() ?: __('store.blog_intro'),
            'tags' => [trans_choice('store.blog_posts_count', $listingCount, ['count' => $listingCount])],
        ])

        <nav class="blog-tabs" aria-label="{{ __('store.blog_title') }}">
            <a href="{{ route('blog.index') }}" class="blog-tab {{ $activeCategory ? '' : 'is-active' }}">
                {{ __('store.blog_all') }}
            </a>
            @foreach ($categories as $category)
                <a
                    href="{{ route('blog.category', $category->slug) }}"
                    class="blog-tab {{ $activeCategory?->id === $category->id ? 'is-active' : '' }}"
                >
                    {{ $category->localizedName() }}
                    <span class="blog-tab-count">{{ $category->posts_count }}</span>
                </a>
            @endforeach
        </nav>

        @if ($posts->isEmpty())
            <p class="empty-state">{{ $activeCategory ? __('store.blog_empty_category') : __('store.blog_empty') }}</p>
        @else
            <div class="blog-grid blog-grid--index">
                @foreach ($posts as $index => $post)
                    @include('blog.partials.card', ['post' => $post, 'lazy' => $index > 1])
                @endforeach
            </div>

            @include('partials.pager', ['paginator' => $posts])
        @endif
    </div>
@endsection
