<article @class(['blog-card', 'blog-card--featured' => $featured ?? false])>
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
        <div class="blog-card-body">
            <span class="blog-card-headrow">
            <span class="blog-card-meta">
                <span class="blog-card-category">{{ $post->category?->localizedName() }}</span>
                <time class="blog-card-date" datetime="{{ $post->published_at?->toDateString() }}">
                    {{ $post->published_at?->translatedFormat('j F Y') }}
                </time>
            </span>
            <span class="blog-card-chips">
                @php
                    // Counted by the listing; counted here for the card's
                    // other hosts (related posts, product pages).
                    $commentCount = $post->comments_count ?? $post->comments()->visible()->count();
                @endphp
                <span class="blog-card-comments {{ $commentCount > 0 ? 'has-comments' : '' }}"
                      title="{{ trans_choice('store.blog_comments_count', $commentCount, ['count' => $commentCount]) }}">
                    <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                        <path d="M4 5h16v11H9l-5 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    </svg>
                    {{ $commentCount }}
                    <span class="sr-only">{{ trans_choice('store.blog_comments_count', $commentCount, ['count' => $commentCount]) }}</span>
                </span>
                <span class="blog-card-read-time">
                    <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ __('store.blog_read_time', ['min' => $post->readingMinutes()]) }}
                </span>
            </span>
            </span>
            {{-- A heading, not a span: the index's outline should list its
                 posts, not jump from the h1 to the footer columns. --}}
            <h2 class="blog-card-title">{{ $post->localizedTitle() }}</h2>
            @if ($post->localizedExcerpt() !== '')
                <span class="blog-card-excerpt">{{ $post->localizedExcerpt() }}</span>
            @endif
            <span class="blog-card-more">{{ __('store.blog_read') }}</span>
        </div>
    </a>
</article>
