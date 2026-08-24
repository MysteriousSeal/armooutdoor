@extends('layouts.app')

@section('title', ($activeCategory ? $activeCategory->localizedName().' — ' : '').__('store.blog_title').' — '.config('app.name'))
@section('meta_description', $activeCategory?->localizedDescription() ?: __('store.blog_intro'))
@section('canonical', $activeCategory ? route('blog.index', ['categorie' => $activeCategory->slug]) : route('blog.index'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/blog.css') }}">
@endpush

@section('content')
    <div class="container">
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

        <header class="page-head">
            <p class="home-kicker">{{ __('store.hero_kicker') }}</p>
            <h2 class="page-title">{{ $activeCategory?->localizedName() ?? __('store.blog_title') }}</h2>
            <p class="page-lede">{{ $activeCategory?->localizedDescription() ?: __('store.blog_intro') }}</p>
        </header>

        <nav class="sort-tabs blog-tabs" aria-label="{{ __('store.blog_title') }}">
            <a href="{{ route('blog.index') }}" class="sort-tab {{ $activeCategory ? '' : 'active' }}">
                {{ __('store.blog_all') }}
            </a>
            @foreach ($categories as $category)
                <a
                    href="{{ route('blog.index', ['categorie' => $category->slug]) }}"
                    class="sort-tab {{ $activeCategory?->id === $category->id ? 'active' : '' }}"
                >
                    {{ $category->localizedName() }}
                    <span class="blog-tab-count">{{ $category->posts_count }}</span>
                </a>
            @endforeach
        </nav>

        @if ($posts->isEmpty())
            <p class="empty-state">{{ $activeCategory ? __('store.blog_empty_category') : __('store.blog_empty') }}</p>
        @else
            <div class="blog-grid">
                @foreach ($posts as $index => $post)
                    @include('blog.partials.card', ['post' => $post, 'lazy' => $index > 1])
                @endforeach
            </div>

            @include('partials.pager', ['paginator' => $posts])
        @endif
    </div>
@endsection
