{{-- One comment: who, when, what — the shop's answers wear the badge.
     Pruning happens in the back office, never on the page. --}}
<article class="blog-comment-card {{ $comment->is_admin ? 'blog-comment-card--shop' : '' }}">
    {{-- The shop signs its monogram; everyone else their initial. --}}
    <span class="blog-comment-avatar {{ $comment->is_admin ? 'is-shop' : '' }}" aria-hidden="true">{{ $comment->is_admin ? 'AO' : \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($comment->authorLabel(), 0, 1)) }}</span>
    <div class="blog-comment-main">
        <header class="blog-comment-head">
            <span class="blog-comment-author">{{ $comment->authorLabel() }}</span>
            @if ($comment->is_admin)
                <span class="blog-comment-badge">Boutique</span>
            @endif
            <time class="blog-comment-date" datetime="{{ $comment->created_at->toIso8601String() }}">
                {{ $comment->created_at->translatedFormat('j F Y') }} à {{ $comment->created_at->format('H:i') }}
            </time>
            @if (auth()->user()?->isAdmin() && $comment->reference)
                {{-- Admin eyes only: the handle the back-office search takes. --}}
                <code class="blog-comment-ref" title="Référence pour la recherche admin">{{ $comment->reference }}</code>
            @endif
        </header>
        <p class="blog-comment-body">{!! nl2br(e($comment->body)) !!}</p>
    </div>
</article>
