@extends('layouts.app')

@section('title', $post->metaTitle().' — '.config('app.name'))
@section('meta_description', $post->metaDescription())
@section('canonical', route('blog.show', $post->slug))
@section('og_type', 'article')

@if (! empty($preview))
    @section('robots', 'noindex, nofollow')
@endif

@push('head')
    {{-- The og counterparts of the JSON-LD dates: search reads the schema,
         the social previews read these. --}}
    <meta property="article:published_time" content="{{ $post->published_at?->toAtomString() }}">
    <meta property="article:modified_time" content="{{ ($post->updated_at ?? $post->published_at)?->toAtomString() }}">
@endpush
@section('og_image', $post->heroUrl())
@section('og_image_alt', $post->localizedTitle())

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/blog.css') }}">
    <script type="application/ld+json">
        {!! json_encode(\App\Support\ArticleSchema::for($post), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
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
    @if (! empty($preview))
        {{-- Said before the page starts pretending: this is the draft. --}}
        <div class="blog-preview-banner" role="status">
            <span class="blog-preview-banner-badge">Aperçu</span>
            <span>Brouillon non publié, visible uniquement connecté à l'administration.</span>
        </div>
    @endif

    <div class="container blog-article-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ route('blog.index') }}">{{ __('store.blog_title') }}</a>
            @if ($post->category)
                <span class="breadcrumbs-sep" aria-hidden="true">/</span>
                <a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->localizedName() }}</a>
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

                {{-- Le crédit accompagne l'image : sans visuel, il ne crédite
                     rien et ne s'affiche pas. --}}
                @if ($post->image && $post->image_credit)
                    <p class="blog-article-credit">{{ $post->imageCreditLine() }}</p>
                @endif
                <div class="blog-article-banner-copy">
                    @if ($post->category || $post->published_at)
                        <dl class="blog-article-byline">
                            @if ($post->category)
                                <div class="blog-article-byline-cell">
                                    <dt>{{ __('store.blog_category_label') }}</dt>
                                    <dd>
                                        <a href="{{ route('blog.category', $post->category->slug) }}">
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

            @if ($post->sourcesList() !== [])
                {{-- The receipts: where the article's claims come from,
                     dressed like the post's other sections. --}}
                <section class="blog-article-sources" aria-labelledby="blog-sources-title">
                    <header class="blog-article-section-head">
                        <h2 class="blog-section-title" id="blog-sources-title">{{ __('store.blog_sources') }}</h2>
                    </header>
                    <ul class="blog-sources-grid">
                        @foreach ($post->sourcesList() as $source)
                            <li>
                                <a href="{{ $source['url'] }}" target="_blank" rel="noopener" class="blog-source-card">
                                    <span class="blog-source-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="blog-source-copy">
                                        <span class="blog-source-label">{{ $source['label'] }}</span>
                                        <span class="blog-source-host">{{ $source['host'] }}</span>
                                    </span>
                                    <svg class="blog-source-arrow" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                        <path d="M7 17 17 7M9 7h8v8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

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
                            @include('partials.product-card', ['product' => $product, 'lazy' => true, 'headingLevel' => 'h3'])
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
