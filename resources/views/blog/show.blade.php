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
    <div class="container blog-article-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ route('blog.index') }}">{{ __('store.blog_title') }}</a>
            @if ($post->category)
                <span class="breadcrumbs-sep" aria-hidden="true">/</span>
                <a href="{{ route('blog.index', ['categorie' => $post->category->slug]) }}">{{ $post->category->localizedName() }}</a>
            @endif
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ $post->localizedTitle() }}</span>
        </nav>

        <article class="blog-article">
            <header
                class="blog-article-banner{{ $post->image ? ' has-image' : '' }}"
                @if ($post->image) style="--blog-hero-image: url('{{ $post->heroUrl() }}')" @endif
            >
                @if ($post->image)
                    <div class="blog-article-banner-overlay" aria-hidden="true"></div>
                @endif
                <div class="blog-article-banner-copy">
                    @if ($post->category || $post->published_at)
                        <dl class="blog-article-byline">
                            @if ($post->category)
                                <div class="blog-article-byline-cell">
                                    <dt>{{ __('store.blog_category_label') }}</dt>
                                    <dd>
                                        <a href="{{ route('blog.index', ['categorie' => $post->category->slug]) }}">
                                            {{ $post->category->localizedName() }}
                                        </a>
                                    </dd>
                                </div>
                            @endif
                            @if ($post->published_at)
                                <div class="blog-article-byline-cell">
                                    <dt>{{ __('store.blog_published_label') }}</dt>
                                    <dd>
                                        <time datetime="{{ $post->published_at->toDateString() }}">
                                            {{ $post->published_at->translatedFormat('j F Y') }}
                                        </time>
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    @endif
                    <h1 class="blog-article-title">
                        <span class="blog-article-title-accent">{{ $post->localizedTitle() }}</span>
                    </h1>
                    @if ($post->localizedExcerpt() !== '')
                        <p class="blog-article-lede">{{ $post->localizedExcerpt() }}</p>
                    @endif
                </div>
            </header>

            <div class="blog-article-main">
                <div class="blog-article-body">
                    {!! $post->localizedBody() !!}
                </div>
            </div>

            <aside class="blog-article-ask">
                <div class="blog-article-ask-copy">
                    <p class="blog-article-ask-kicker">{{ __('store.blog_title') }}</p>
                    <p class="blog-article-ask-title">{{ __('store.blog_question') }}</p>
                </div>
                <a href="{{ route('contact.show') }}" class="btn btn-primary">{{ __('store.blog_contact_us') }}</a>
            </aside>

            @if ($post->products->isNotEmpty())
                <section class="blog-article-products" aria-labelledby="blog-products-title">
                    <header class="blog-article-section-head">
                        <h2 class="blog-section-title" id="blog-products-title">{{ __('store.blog_related_products') }}</h2>
                    </header>
                    <div class="product-grid">
                        @foreach ($post->products as $product)
                            @include('partials.product-card', ['product' => $product, 'lazy' => true])
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($related->isNotEmpty())
                <section class="blog-article-related" aria-labelledby="blog-related-title">
                    <header class="blog-article-section-head">
                        <h2 class="blog-section-title" id="blog-related-title">{{ __('store.blog_related_posts') }}</h2>
                    </header>
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
