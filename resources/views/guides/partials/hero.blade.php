{{-- Shared chrome of a single guide: the trail, then the shop's page hero. --}}
<nav class="breadcrumbs" aria-label="breadcrumb">
    <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <a href="{{ route('guides.index') }}">Guides</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <span>{{ $crumb }}</span>
</nav>

@if (! empty($imageUrl))
    {{-- A guide with an illustration shows it whole, as a plate: a real image
         at its own ratio rather than a background cropped to the copy's
         height. Category pages keep the shared hero below. --}}
    <header class="cat-hero cat-hero--plate">
        <div class="cat-hero-copy">
            <p class="cat-hero-kicker">{{ $kicker }}</p>
            <h1 class="cat-hero-title">
                <span class="cat-hero-title-accent">{{ $title }}</span>
            </h1>
        </div>
        <figure class="cat-hero-plate">
            <img
                src="{{ $imageUrl }}"
                width="{{ $imageWidth ?? 1600 }}"
                height="{{ $imageHeight ?? 901 }}"
                alt="{{ $imageAlt ?? '' }}"
                fetchpriority="high"
                decoding="async"
            >
        </figure>
        <div class="cat-hero-rail">
            <p class="cat-hero-desc">{{ $lede }}</p>
            @if (! empty($tags))
                <ul class="cat-hero-tags">
                    @foreach ($tags as $tag)
                        <li>{{ $tag }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </header>
@else
    @include('partials.page-hero', [
        'kicker' => $kicker,
        'title' => $title,
        'description' => $lede,
        'tags' => $tags ?? [],
    ])
@endif
