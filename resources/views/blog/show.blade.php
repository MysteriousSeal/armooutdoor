@extends('layouts.app')

@section('title', $post->metaTitle().' — '.config('app.name'))
@section('meta_description', $post->metaDescription())
@section('canonical', route('blog.show', $post->slug))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/blog.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => __('store.blog_title'), 'item' => route('blog.index')],
                ['@@type' => 'ListItem', 'position' => 3, 'name' => $post->localizedTitle(), 'item' => route('blog.show', $post->slug)],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ route('blog.index') }}">{{ __('store.blog_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ $post->localizedTitle() }}</span>
        </nav>

        <article class="blog-article">
            <header class="blog-article-head">
                <p class="blog-article-meta">
                    <a href="{{ route('blog.index', ['categorie' => $post->category?->slug]) }}" class="blog-article-category">
                        {{ $post->category?->localizedName() }}
                    </a>
                    <span class="breadcrumbs-sep" aria-hidden="true">·</span>
                    <time datetime="{{ $post->published_at?->toDateString() }}">
                        {{ $post->published_at?->translatedFormat('j F Y') }}
                    </time>
                </p>
                <h2 class="blog-article-title">{{ $post->localizedTitle() }}</h2>
                @if ($post->localizedExcerpt() !== '')
                    <p class="blog-article-lede">{{ $post->localizedExcerpt() }}</p>
                @endif
            </header>

            @if ($post->image)
                <figure class="blog-article-hero">
                    <img src="{{ $post->heroUrl() }}" alt="" width="1600" height="900">
                </figure>
            @endif

            <div class="blog-article-body">
                {!! $post->localizedBody() !!}
            </div>

            @if ($post->products->isNotEmpty())
                <section class="blog-article-products" aria-labelledby="blog-products-title">
                    <h3 class="blog-section-title" id="blog-products-title">{{ __('store.blog_related_products') }}</h3>
                    <div class="product-grid">
                        @foreach ($post->products as $product)
                            @include('partials.product-card', ['product' => $product, 'lazy' => true])
                        @endforeach
                    </div>
                </section>
            @endif

            <aside class="blog-article-ask">
                <p>{{ __('store.blog_question') }}</p>
                <a href="{{ route('contact.show') }}" class="btn btn-secondary">{{ __('store.blog_contact_us') }}</a>
            </aside>

            @if ($related->isNotEmpty())
                <section class="blog-article-related" aria-labelledby="blog-related-title">
                    <h3 class="blog-section-title" id="blog-related-title">{{ __('store.blog_related_posts') }}</h3>
                    <div class="blog-grid">
                        @foreach ($related as $other)
                            @include('blog.partials.card', ['post' => $other, 'lazy' => true])
                        @endforeach
                    </div>
                </section>
            @endif

            <p class="blog-article-back">
                <a href="{{ route('blog.index') }}">← {{ __('store.blog_back_to_list') }}</a>
            </p>
        </article>
    </div>
@endsection
