@php
    $heroImage = $imageUrl ?? null;
    $heroTags = $tags ?? [];
    $heroDescription = $description ?? '';
    $titleNoWrap = $titleNoWrap ?? false;
@endphp
<header class="cat-hero {{ $heroImage ? 'has-image' : '' }}" @if ($heroImage) style="--cat-hero-image: url('{{ $heroImage }}')" @endif>
    @if ($heroImage)
        <div class="cat-hero-overlay" aria-hidden="true"></div>
    @endif
    <div class="cat-hero-copy {{ $titleNoWrap ? 'cat-hero-copy--wide' : '' }}">
        <p class="cat-hero-kicker">{{ $kicker }}</p>
        <h1 class="cat-hero-title {{ $titleNoWrap ? 'cat-hero-title--nowrap' : '' }}">
            <span class="cat-hero-title-accent">{{ $title }}</span>
        </h1>
        @if ($heroDescription !== '')
            <p class="cat-hero-desc">{{ $heroDescription }}</p>
        @endif
        @if ($heroTags !== [])
            <ul class="cat-hero-tags">
                @foreach ($heroTags as $tag)
                    <li>{{ $tag }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</header>
