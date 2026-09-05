{{-- One comment: who, when, what — the shop's answers wear the badge,
     and an admin can prune any comment from the page itself. --}}
<article class="blog-comment-card {{ $comment->is_admin ? 'blog-comment-card--shop' : '' }}">
    <span class="blog-comment-avatar {{ $comment->is_admin ? 'is-shop' : '' }}" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($comment->authorLabel(), 0, 1)) }}</span>
    <div class="blog-comment-main">
        <header class="blog-comment-head">
            <span class="blog-comment-author">{{ $comment->authorLabel() }}</span>
            @if ($comment->is_admin)
                <span class="blog-comment-badge">Boutique</span>
            @endif
            <time class="blog-comment-date" datetime="{{ $comment->created_at->toDateString() }}">
                {{ $comment->created_at->translatedFormat('j F Y') }}
            </time>
            @if (auth()->user()?->isAdmin())
                <form method="POST" action="{{ route('blog.comments.destroy', $comment) }}" class="blog-comment-delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-secondary" title="Supprimer ce commentaire">&times;</button>
                </form>
            @endif
        </header>
        <p class="blog-comment-body">{!! nl2br(e($comment->body)) !!}</p>
    </div>
</article>
