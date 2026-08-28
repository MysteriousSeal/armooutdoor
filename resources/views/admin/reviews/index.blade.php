@extends('layouts.admin')

@section('title', 'Reviews')

@php
    $productOptions = $products->map(fn ($option) => [
        'id' => $option->id,
        'label' => $option->localizedName(),
        'name' => $option->localizedName(),
        'sku' => $option->sku ?: '',
        'meta' => $option->sku ? 'SKU '.$option->sku : '',
        'image' => $option->image ? $option->imageUrl() : '',
        'search' => $option->localizedName().' '.($option->sku ?? ''),
    ]);
    $selectedCreateProduct = $products->firstWhere('id', (int) old('product_id'));
@endphp

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
                <button type="button" class="btn btn-primary" data-modal-open="review-create">Add review</button>
            </div>
        </header>

        {{-- A review posted on a marketplace, copied over by hand so the
             product page here carries it too. --}}
        <dialog id="review-create" class="modal admin-review-create" aria-labelledby="review-create-title">
            <form method="POST" action="{{ route('admin.reviews.store') }}">
                @csrf
                <input type="hidden" name="_form" value="review-create">
                <p class="modal-kicker">Posted on a marketplace</p>
                <h3 class="modal-title" id="review-create-title">Add a review</h3>

                <div class="form-group">
                    <label for="create-product">Product</label>
                    <div class="search-select" data-search-select data-source="products">
                        <input type="hidden" name="product_id" value="{{ old('product_id') }}">
                        <input
                            type="text"
                            id="create-product"
                            class="form-control search-select-input"
                            placeholder="Search by name or SKU…"
                            value="{{ $selectedCreateProduct?->localizedName() ?? '' }}"
                            autocomplete="off"
                            spellcheck="false"
                        >
                        <ul class="search-select-list" hidden></ul>
                    </div>
                    @error('product_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-review-create-row">
                    <div class="form-group">
                        <label for="create-author">Customer name</label>
                        <input
                            id="create-author"
                            type="text"
                            name="author_name"
                            class="form-control"
                            placeholder="As shown on the marketplace"
                            value="{{ old('author_name') }}"
                            maxlength="100"
                            required
                        >
                        @error('author_name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label id="create-rating-label">Rating</label>
                        <div class="admin-star-picker" role="radiogroup" aria-labelledby="create-rating-label">
                            @foreach ([5, 4, 3, 2, 1] as $stars)
                                <input
                                    type="radio"
                                    id="create-rating-{{ $stars }}"
                                    name="rating"
                                    value="{{ $stars }}"
                                    @checked((int) old('rating') === $stars)
                                    required
                                >
                                <label for="create-rating-{{ $stars }}" aria-label="{{ $stars }} {{ $stars === 1 ? 'star' : 'stars' }}">★</label>
                            @endforeach
                        </div>
                        @error('rating') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="create-comment">Review</label>
                    <textarea
                        id="create-comment"
                        name="comment"
                        class="form-control"
                        rows="4"
                        placeholder="The review as the customer wrote it…"
                        maxlength="2000"
                        required
                    >{{ old('comment') }}</textarea>
                    @error('comment') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-review-create-row">
                    <div class="form-group">
                        <label for="create-source">Source <span class="admin-field-optional">optional</span></label>
                        <input
                            id="create-source"
                            type="text"
                            name="source"
                            class="form-control"
                            placeholder="Naturabuy, Amazon…"
                            value="{{ old('source') }}"
                            maxlength="50"
                        >
                        @error('source') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label for="create-posted-at">Posted on <span class="admin-field-optional">optional</span></label>
                        <input
                            id="create-posted-at"
                            type="date"
                            name="posted_at"
                            class="form-control"
                            value="{{ old('posted_at') }}"
                            max="{{ now()->toDateString() }}"
                        >
                        @error('posted_at') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Add review</button>
                </div>
            </form>
        </dialog>

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
                                @elseif ($review->isManual())
                                    {{ $review->author_name }}
                                @else
                                    Deleted customer
                                @endif
                                <span aria-hidden="true">·</span>
                                {{ $review->created_at->format('d M Y') }}
                                @if ($review->order)
                                    <span aria-hidden="true">·</span>
                                    <a href="{{ route('admin.orders.show', $review->order) }}">{{ $review->order->number }}</a>
                                @endif
                                @if ($review->isManual())
                                    <span class="admin-review-source">{{ $review->source ?? 'Added manually' }}</span>
                                @endif
                            </p>
                            @if (filled($review->comment))
                                <p class="admin-review-comment">{{ $review->comment }}</p>
                            @else
                                <p class="admin-review-comment is-empty">No comment — rating only.</p>
                            @endif
                        </div>

                        <div class="admin-review-actions">
                            @if ($product)
                                {{-- Straight to the review's own section of the page, not the top of it. --}}
                                <a
                                    href="{{ route('products.show', $product) }}#product-reviews-title"
                                    class="btn btn-sm btn-secondary admin-review-view"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    View in shop
                                    <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                        <path d="M14 5h5v5M19 5l-8 8M9 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19h11a1.5 1.5 0 0 0 1.5-1.5V15" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            @endif
                            @if (auth()->user()->isOwner())
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
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @include('admin.partials.pager', ['paginator' => $reviews])
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-search-select.js') }}"></script>
    <script>
        AdminSearchSelect.catalogs.products = @json($productOptions);
        AdminSearchSelect.mountAll();
    </script>
    @if ($errors->any() && old('_form') === 'review-create')
        {{-- A refused submission reopens the modal it came from, errors and
             typed values still in place. --}}
        <script>
            document.getElementById('review-create')?.showModal();
        </script>
    @endif
@endpush
