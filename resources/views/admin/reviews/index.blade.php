@extends('layouts.admin')

@section('title', 'Reviews')

@section('content')
    <div class="admin-list-page admin-reviews-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">Reviews</h2>
                    <p class="admin-list-lede">
                        What customers wrote about the products, across the whole catalogue. Deleting a review removes it from the product page for good.
                    </p>
                </div>
            </div>
        </header>

        @if ($total > 0)
            <div class="admin-review-stats">
                <div class="admin-review-stat">
                    <span class="admin-review-stat-value">
                        {{ number_format($average, 1) }}
                        <span class="admin-review-stars" aria-hidden="true">{{ str_repeat('★', (int) round($average)) }}{{ str_repeat('☆', 5 - (int) round($average)) }}</span>
                    </span>
                    <span class="admin-review-stat-label">Average rating</span>
                </div>
                <div class="admin-review-stat">
                    <span class="admin-review-stat-value">{{ number_format($total) }}</span>
                    <span class="admin-review-stat-label">{{ $total === 1 ? 'Review posted' : 'Reviews posted' }}</span>
                </div>
                <div class="admin-review-stat admin-review-stat--bars">
                    @foreach ($ratingCounts as $stars => $count)
                        <div class="admin-review-bar-row">
                            <span class="admin-review-bar-stars" aria-hidden="true">{{ $stars }} ★</span>
                            <span class="sr-only">{{ $stars }} {{ $stars === 1 ? 'star' : 'stars' }}</span>
                            <span class="admin-review-bar-track">
                                <span class="admin-review-bar-fill" style="width: {{ $total > 0 ? round($count / $total * 100) : 0 }}%"></span>
                            </span>
                            <span class="admin-review-bar-count">{{ number_format($count) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <nav class="admin-subtabs" aria-label="Filter by rating">
            <a href="{{ route('admin.reviews.index', array_filter(['search' => $search ?: null])) }}" class="{{ $rating === 0 ? 'active' : '' }}">
                All <span class="admin-tab-count">{{ number_format($total) }}</span>
            </a>
            @foreach ($ratingCounts as $stars => $count)
                <a href="{{ route('admin.reviews.index', array_filter(['rating' => $stars, 'search' => $search ?: null])) }}" class="{{ $rating === $stars ? 'active' : '' }}">
                    {{ $stars }} ★ <span class="admin-tab-count">{{ number_format($count) }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.reviews.index') }}" class="admin-filter-bar">
            @if ($rating > 0)
                <input type="hidden" name="rating" value="{{ $rating }}">
            @endif
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-field-label" for="review-search">Search</label>
                    <input
                        id="review-search"
                        type="search"
                        name="search"
                        class="form-control admin-toolbar-search"
                        placeholder="Product, reference, customer name or email…"
                        value="{{ $search }}"
                    >
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                    @if ($search !== '')
                        <a href="{{ route('admin.reviews.index', array_filter(['rating' => $rating ?: null])) }}" class="admin-link">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($total === 0)
            <div class="empty-state">
                <p>No reviews yet. They appear here as customers post them from the product pages.</p>
            </div>
        @elseif ($reviews->isEmpty())
            <div class="empty-state">
                <p>
                    @if ($search !== '')
                        Nothing matches “{{ $search }}”{{ $rating > 0 ? ' with '.$rating.' star'.($rating > 1 ? 's' : '') : '' }}.
                    @else
                        No {{ $rating }}-star reviews yet.
                    @endif
                </p>
            </div>
        @else
            <ul class="admin-review-list">
                @foreach ($reviews as $review)
                    @php($product = $review->product)
                    <li class="admin-review-card">
                        @if ($product)
                            <a href="{{ route('admin.products.edit', $product) }}" class="admin-review-media">
                                <img src="{{ $product->imageUrl() }}" alt="" width="56" height="56" loading="lazy">
                            </a>
                        @else
                            <span class="admin-review-media is-empty"></span>
                        @endif

                        <div class="admin-review-main">
                            <div class="admin-review-head">
                                <p class="admin-review-product">
                                    @if ($product)
                                        <a href="{{ route('admin.products.edit', $product) }}">{{ $product->localizedName() }}</a>
                                    @else
                                        Deleted product
                                    @endif
                                </p>
                                <span class="admin-review-rating" role="img" aria-label="{{ $review->rating }} out of 5">
                                    <span class="admin-review-stars" aria-hidden="true">{{ str_repeat('★', $review->rating) }}</span><span class="admin-review-stars is-empty" aria-hidden="true">{{ str_repeat('★', 5 - $review->rating) }}</span>
                                </span>
                            </div>
                            <p class="admin-review-meta">
                                @if ($review->user)
                                    <a href="{{ route('admin.customers.show', $review->user) }}">{{ $review->user->name }}</a>
                                @else
                                    Deleted customer
                                @endif
                                <span aria-hidden="true">·</span>
                                {{ $review->created_at->format('d M Y') }}
                                @if ($review->order)
                                    <span aria-hidden="true">·</span>
                                    <a href="{{ route('admin.orders.show', $review->order) }}">{{ $review->order->number }}</a>
                                @endif
                            </p>
                            @if (filled($review->comment))
                                <p class="admin-review-comment">{{ $review->comment }}</p>
                            @else
                                <p class="admin-review-comment is-empty">No comment — rating only.</p>
                            @endif
                        </div>

                        @if (auth()->user()->isOwner())
                            <div class="admin-review-actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-modal-open="review-delete-{{ $review->id }}">Delete</button>
                                <dialog id="review-delete-{{ $review->id }}" class="modal" aria-labelledby="review-delete-{{ $review->id }}-title">
                                    <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="back" value="{{ url()->full() }}">
                                        <p class="modal-kicker">{{ $product?->localizedName() ?? 'Deleted product' }}</p>
                                        <h3 class="modal-title" id="review-delete-{{ $review->id }}-title">Delete this review?</h3>
                                        <p class="modal-body">It disappears from the product page and can't be restored. The customer isn't notified.</p>
                                        <div class="modal-actions">
                                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                                            <button type="submit" class="btn btn-primary">Delete review</button>
                                        </div>
                                    </form>
                                </dialog>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            @include('admin.partials.pager', ['paginator' => $reviews])
        @endif
    </div>
@endsection
