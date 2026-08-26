@extends('layouts.admin')

@section('title', 'Blog — Admin')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Content</p>
                    <h2 class="admin-list-title">Blog</h2>
                    <p class="admin-list-lede">Guides, news, hands-on and legal posts.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('blog.index') }}" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">View blog</a>
                    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary">New post</a>
                </div>
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Blog tabs">
            <a href="{{ route('admin.blog.index') }}" class="{{ $tab === 'all' ? 'active' : '' }}">
                All <span class="admin-tab-count">{{ number_format($allCount) }}</span>
            </a>
            <a href="{{ route('admin.blog.index', ['tab' => 'published']) }}" class="{{ $tab === 'published' ? 'active' : '' }}">
                Published <span class="admin-tab-count">{{ number_format($publishedCount) }}</span>
            </a>
            {{-- Programmé : publié mais daté du futur. Sans cet onglet, l'état
                 n'apparaît nulle part. --}}
            <a href="{{ route('admin.blog.index', ['tab' => 'scheduled']) }}" class="{{ $tab === 'scheduled' ? 'active' : '' }}">
                Scheduled <span class="admin-tab-count">{{ number_format($scheduledCount) }}</span>
            </a>
            <a href="{{ route('admin.blog.index', ['tab' => 'draft']) }}" class="sits-apart {{ $tab === 'draft' ? 'active' : '' }}">
                Drafts <span class="admin-tab-count">{{ number_format($draftCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.blog.index') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-field-label" for="blog-search">Search</label>
                    <input id="blog-search" type="search" name="search" class="form-control admin-toolbar-search" placeholder="Title…" value="{{ $search }}">
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </div>
        </form>

        @if ($posts->isEmpty())
            <p class="empty-state">No posts yet.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th>Products</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posts as $post)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.blog.edit', $post) }}">{{ $post->localizedTitle() }}</a>
                                </td>
                                <td>{{ $post->category?->localizedName() }}</td>
                                <td>
                                    @if ($post->isVisible())
                                        <span class="order-chip order-chip--shipped">Published</span>
                                    @elseif ($post->isScheduled())
                                        <span class="order-chip order-chip--preparing">Scheduled</span>
                                    @else
                                        <span class="order-chip order-chip--draft">Draft</span>
                                    @endif
                                </td>
                                <td>{{ $post->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $post->products()->count() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $posts])
        @endif
    </div>
@endsection
