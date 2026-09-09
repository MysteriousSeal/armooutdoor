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
            {{-- Three cards on the shop's usual stat grid: what the reviews
                 say on average, how they are spread across the stars, and
                 where they came from. The last two are the filters as well
                 as the counts, so the numbers you are already reading are
                 the thing you click. --}}
            <div class="admin-stat-grid admin-review-stats">
                <div class="admin-stat-card admin-review-average">
                    <span class="admin-stat-label">Average rating</span>
                    <span class="admin-stat-value">
                        {{ number_format($average, 2) }}
                        @include('admin.partials.stars', ['value' => $average])
                    </span>
                    <span class="admin-stat-value--sm">
                        {{ number_format($total) }} {{ $total === 1 ? 'review' : 'reviews' }} posted
                    </span>

                    {{-- What the average cannot say: how much of the catalogue
                         has anything written about it at all. --}}
                    <dl class="admin-review-coverage">
                        <div>
                            <dt>Products reviewed</dt>
                            <dd>{{ number_format($reviewedProducts) }}</dd>
                        </div>
                        <div>
                            <dt>Products in catalogue</dt>
                            <dd>{{ number_format($productCount) }}</dd>
                        </div>
                        <div>
                            <dt>Coverage</dt>
                            <dd>{{ $productCount > 0 ? round($reviewedProducts / $productCount * 100) : 0 }}%</dd>
                        </div>
                    </dl>
                </div>

                <div class="admin-stat-card admin-stat-card--breakdown">
                    <span class="admin-stat-label">By rating</span>
                    <ul class="admin-stat-parts admin-review-breakdown">
                        @foreach ($ratingCounts as $stars => $count)
                            @php($active = $rating === $stars)
                            <li>
                                <a
                                    href="{{ route('admin.reviews.index', array_filter([
                                        'rating' => $active ? null : $stars,
                                        'channel' => $channel ?: null,
                                        'search' => $search ?: null,
                                    ])) }}"
                                    class="admin-review-row {{ $active ? 'is-active' : '' }}"
                                    @if ($active) aria-current="true" @endif
                                >
                                    <span class="admin-review-row-name">
                                        <span aria-hidden="true">{{ $stars }} ★</span>
                                        <span class="sr-only">{{ $stars }} {{ $stars === 1 ? 'star' : 'stars' }}</span>
                                        <span class="admin-review-bar-track">
                                            <span class="admin-review-bar-fill" style="width: {{ round($count / $total * 100) }}%"></span>
                                        </span>
                                    </span>
                                    <span class="admin-review-row-count">{{ number_format($count) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="admin-stat-card admin-stat-card--breakdown">
                    <span class="admin-stat-label">By channel</span>
                    <ul class="admin-stat-parts admin-review-breakdown admin-review-channels">
                        @foreach ($channels as $row)
                            @php($active = $channel === $row['key'])
                            <li>
                                <a
                                    href="{{ route('admin.reviews.index', array_filter([
                                        'channel' => $active ? null : $row['key'],
                                        'rating' => $rating ?: null,
                                        'search' => $search ?: null,
                                    ])) }}"
                                    class="admin-review-row {{ $active ? 'is-active' : '' }}"
                                    @if ($active) aria-current="true" @endif
                                >
                                    <span class="admin-review-row-name">
                                        {{ $row['label'] }}
                                        @if ($row['direct'])
                                            <span class="admin-review-row-note">on the shop</span>
                                        @endif
                                    </span>
                                    <span class="admin-review-row-count">{{ number_format($row['total']) }}</span>
                                    <span class="admin-review-row-share">{{ round($row['total'] / $total * 100) }}%</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('admin.reviews.index') }}" class="admin-filter-bar">
            @if ($rating > 0)
                <input type="hidden" name="rating" value="{{ $rating }}">
            @endif
            @if ($channel !== '')
                <input type="hidden" name="channel" value="{{ $channel }}">
            @endif
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-field-label" for="review-search">Search</label>
                    <input
                        id="review-search"
                        type="search"
                        name="search"
                        class="form-control admin-toolbar-search"
                        placeholder="Product, reference, customer name, email or marketplace…"
                        value="{{ $search }}"
                    >
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                    @if ($search !== '' || $channel !== '' || $rating > 0)
                        {{-- One way out for all three. Which filters are on is
                             legible from the cards above, so the bar carries
                             the button and not a second copy of the state. --}}
                        <a href="{{ route('admin.reviews.index') }}" class="btn btn-secondary">Clear filters</a>
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
                        Nothing matches “{{ $search }}”{{ $rating > 0 ? ' with '.$rating.' star'.($rating > 1 ? 's' : '') : '' }}{{ $channel !== '' ? ' from '.$channelLabel : '' }}.
                    @elseif ($channel !== '')
                        No {{ $rating > 0 ? $rating.'-star ' : '' }}reviews from {{ $channelLabel }}.
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
                            <p class="admin-review-product">
                                @if ($product)
                                    <a href="{{ route('admin.products.edit', $product) }}">{{ $product->localizedName() }}</a>
                                @else
                                    Deleted product
                                @endif
                            </p>
                            {{-- Who, when, and where it came from. The name is the
                                 one thing here worth reading at a glance, so it is
                                 the only one set in the page's own colour. --}}
                            <p class="admin-review-meta">
                                <span class="admin-review-who">
                                    @if ($review->user)
                                        <a href="{{ route('admin.customers.show', $review->user) }}">{{ $review->user->name }}</a>
                                    @elseif ($review->isManual())
                                        {{ $review->author_name }}
                                    @else
                                        Deleted customer
                                    @endif
                                </span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $review->created_at->format('d M Y') }}</span>
                                @if ($review->order)
                                    <span aria-hidden="true">·</span>
                                    <a href="{{ route('admin.orders.show', $review->order) }}">{{ $review->order->number }}</a>
                                @endif
                                @if ($review->isManual())
                                    <span class="admin-review-source">{{ $review->source ?? 'Added manually' }}</span>
                                @endif
                            </p>
                            {{-- The words are what the row is for: set as a quote,
                                 against a rule, at reading size. --}}
                            @if (filled($review->comment))
                                <blockquote class="admin-review-comment">{{ $review->comment }}</blockquote>
                            @else
                                <p class="admin-review-comment is-empty">No comment — rating only.</p>
                            @endif
                        </div>

                        {{-- Its own column, so the ratings line up down the page
                             instead of landing wherever the product name ends. --}}
                        <div class="admin-review-rating">
                            @include('admin.partials.stars', ['value' => $review->rating])
                            <span class="admin-review-rating-value">{{ $review->rating }}/5</span>
                        </div>

                        <div class="admin-review-actions">
                            @if ($review->isManual())
                                @php($form = 'review-edit-'.$review->id)
                                {{-- A refused edit reopens this modal with what was
                                     typed still in it; otherwise the fields show the
                                     review as it stands. --}}
                                @php($was = fn (string $field, $current) => old('_form') === $form ? old($field, $current) : $current)
                                @php($editProduct = $products->firstWhere('id', (int) $was('product_id', $review->product_id)))
                                <button type="button" class="btn btn-sm btn-secondary" data-modal-open="{{ $form }}">Edit</button>
                                <dialog id="{{ $form }}" class="modal admin-review-create" aria-labelledby="{{ $form }}-title">
                                    <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="_form" value="{{ $form }}">
                                        <input type="hidden" name="back" value="{{ url()->full() }}">
                                        <p class="modal-kicker">{{ $review->source ?? 'Added manually' }}</p>
                                        <h3 class="modal-title" id="{{ $form }}-title">Edit this review</h3>

                                        <div class="form-group">
                                            <label for="{{ $form }}-product">Product</label>
                                            <div class="search-select" data-search-select data-source="products">
                                                <input type="hidden" name="product_id" value="{{ $was('product_id', $review->product_id) }}">
                                                <input
                                                    type="text"
                                                    id="{{ $form }}-product"
                                                    class="form-control search-select-input"
                                                    placeholder="Search by name or SKU…"
                                                    value="{{ $editProduct?->localizedName() ?? '' }}"
                                                    autocomplete="off"
                                                    spellcheck="false"
                                                >
                                                <ul class="search-select-list" hidden></ul>
                                            </div>
                                            @error('product_id') <p class="form-error">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="admin-review-create-row">
                                            <div class="form-group">
                                                <label for="{{ $form }}-author">Customer name</label>
                                                <input
                                                    id="{{ $form }}-author"
                                                    type="text"
                                                    name="author_name"
                                                    class="form-control"
                                                    value="{{ $was('author_name', $review->author_name) }}"
                                                    maxlength="100"
                                                    required
                                                >
                                                @error('author_name') <p class="form-error">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="form-group">
                                                <label id="{{ $form }}-rating-label">Rating</label>
                                                <div class="admin-star-picker" role="radiogroup" aria-labelledby="{{ $form }}-rating-label">
                                                    @foreach ([5, 4, 3, 2, 1] as $stars)
                                                        <input
                                                            type="radio"
                                                            id="{{ $form }}-rating-{{ $stars }}"
                                                            name="rating"
                                                            value="{{ $stars }}"
                                                            @checked((int) $was('rating', $review->rating) === $stars)
                                                            required
                                                        >
                                                        <label for="{{ $form }}-rating-{{ $stars }}" aria-label="{{ $stars }} {{ $stars === 1 ? 'star' : 'stars' }}">★</label>
                                                    @endforeach
                                                </div>
                                                @error('rating') <p class="form-error">{{ $message }}</p> @enderror
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="{{ $form }}-comment">Review</label>
                                            <textarea
                                                id="{{ $form }}-comment"
                                                name="comment"
                                                class="form-control"
                                                rows="4"
                                                maxlength="2000"
                                                required
                                            >{{ $was('comment', $review->comment) }}</textarea>
                                            @error('comment') <p class="form-error">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="admin-review-create-row">
                                            <div class="form-group">
                                                <label for="{{ $form }}-source">Source <span class="admin-field-optional">optional</span></label>
                                                <input
                                                    id="{{ $form }}-source"
                                                    type="text"
                                                    name="source"
                                                    class="form-control"
                                                    placeholder="Naturabuy, Amazon…"
                                                    value="{{ $was('source', $review->source) }}"
                                                    maxlength="50"
                                                >
                                                @error('source') <p class="form-error">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="form-group">
                                                <label for="{{ $form }}-posted-at">Posted on</label>
                                                <input
                                                    id="{{ $form }}-posted-at"
                                                    type="date"
                                                    name="posted_at"
                                                    class="form-control"
                                                    value="{{ $was('posted_at', $review->created_at->toDateString()) }}"
                                                    max="{{ now()->toDateString() }}"
                                                >
                                                @error('posted_at') <p class="form-error">{{ $message }}</p> @enderror
                                            </div>
                                        </div>

                                        <div class="modal-actions">
                                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save review</button>
                                        </div>
                                    </form>
                                </dialog>
                            @endif
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
                                <button type="button" class="btn btn-sm btn-secondary admin-review-delete" data-modal-open="review-delete-{{ $review->id }}">Delete</button>
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
    <script src="{{ versioned_asset('js/admin-search-select.js') }}"></script>
    <script>
        AdminSearchSelect.catalogs.products = @json($productOptions);
        AdminSearchSelect.mountAll();
    </script>
    @if ($errors->any() && old('_form'))
        {{-- A refused submission reopens the modal it came from, errors and
             typed values still in place. Every form on the page names itself
             after its own dialog, so this holds for the edits too. --}}
        <script>
            document.getElementById(@json(old('_form')))?.showModal();
        </script>
    @endif
@endpush
