@extends('layouts.admin')

@section('title', 'Blog comments')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.blog.index') }}">Blog</a></p>
                    <h2 class="admin-list-title">Comments</h2>
                    <p class="admin-list-lede">Everything readers wrote, newest first. Comments publish themselves; this is where they get pruned.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.blog.index') }}" class="btn btn-secondary">Back to posts</a>
                </div>
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Blog tabs">
            <a href="{{ route('admin.blog.index') }}">Posts</a>
            <a href="{{ route('admin.blog.comments.index') }}" class="active">
                Comments <span class="admin-tab-count">{{ number_format($commentCount) }}</span>
            </a>
        </nav>

        @if (session('status'))
            <p class="admin-flash">{{ session('status') }}</p>
        @endif

        @if ($comments->isEmpty())
            <div class="empty-state">
                <p>No comments yet.</p>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Author</th>
                            <th>Comment</th>
                            <th>Article</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comments as $comment)
                            <tr>
                                <td>
                                    {{ $comment->authorLabel() }}
                                    @if ($comment->is_admin)
                                        <span class="order-chip order-chip--shipped">Shop</span>
                                    @elseif ($comment->user_id === null)
                                        <span class="order-chip order-chip--draft">Guest</span>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($comment->body, 120) }}</td>
                                <td>
                                    <a href="{{ route('blog.show', $comment->post->slug) }}#commentaires" target="_blank" rel="noopener noreferrer">
                                        {{ \Illuminate\Support\Str::limit($comment->post->localizedTitle(), 45) }}
                                    </a>
                                </td>
                                <td>{{ $comment->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="admin-table-actions">
                                        <form method="POST" action="{{ route('admin.blog.comments.destroy', $comment) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-secondary">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $comments])
        @endif
    </div>
@endsection
