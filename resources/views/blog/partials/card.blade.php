<article class="blog-card">
    <a href="{{ route('blog.show', $post->slug) }}" class="blog-card-link">
        <span class="blog-card-media">
            @if ($post->image)
                <img
                    src="{{ $post->cardUrl() }}"
                    alt=""
                    width="800"
                    height="450"
                    @if ($lazy ?? true) loading="lazy" @endif
                >
            @else
                <span class="blog-card-media-empty" aria-hidden="true"></span>
            @endif
        </span>
        <span class="blog-card-body">
            <span class="blog-card-meta">
                <span class="blog-card-category">{{ $post->category?->localizedName() }}</span>
                <time class="blog-card-date" datetime="{{ $post->published_at?->toDateString() }}">
                    {{ $post->published_at?->translatedFormat('j F Y') }}
                </time>
            </span>
            <span class="blog-card-title">{{ $post->localizedTitle() }}</span>
            @if ($post->localizedExcerpt() !== '')
                <span class="blog-card-excerpt">{{ $post->localizedExcerpt() }}</span>
            @endif
            <span class="blog-card-more">{{ __('store.blog_read') }}</span>
        </span>
    </a>
</article>
