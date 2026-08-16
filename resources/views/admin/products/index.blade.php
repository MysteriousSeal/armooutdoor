@extends('layouts.admin')

@section('title', 'Products')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">Products</h2>
                    <p class="admin-list-lede">Everything in the shop, in euros. Add a piece or open one to edit it.</p>
                </div>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Add product</a>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($productCount) }} products</span>
                <span class="admin-list-chip">{{ number_format($disabledCount) }} disabled</span>
                <span class="admin-list-chip">{{ number_format($outOfStockCount) }} out of stock</span>
                <span class="admin-list-chip">{{ number_format($noGtinCount) }} without GTIN</span>
                <span class="admin-list-chip">{{ number_format($noWeightCount) }} without weight</span>
                @if ($search !== '' || $categorySlug !== '')
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Product tabs">
            <a href="{{ route('admin.products.index', array_filter(['tab' => 'active', 'search' => $search ?: null, 'category' => $categorySlug ?: null])) }}" class="{{ $tab === 'active' ? 'active' : '' }}">
                Products <span class="admin-tab-count">{{ number_format($activeCount) }}</span>
            </a>
            <a href="{{ route('admin.products.index', array_filter(['tab' => 'disabled', 'search' => $search ?: null, 'category' => $categorySlug ?: null])) }}" class="{{ $tab === 'disabled' ? 'active' : '' }}">
                Disabled <span class="admin-tab-count">{{ number_format($disabledCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.products.index') }}" class="admin-toolbar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input
                type="search"
                name="search"
                class="form-control"
                placeholder="Search name or slug…"
                value="{{ $search }}"
            >
            <select name="category" class="form-control">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected($categorySlug === $category->slug)>
                        {{ $category->name['fr'] ?? $category->localizedName() }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            @if ($search !== '' || $categorySlug !== '')
                <a href="{{ route('admin.products.index', array_filter(['tab' => $tab !== 'active' ? $tab : null])) }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>

        @if ($products->isEmpty())
            <div class="empty-state">
                <p>{{ $tab === 'disabled' ? 'No disabled products.' : 'No products found.' }}</p>
                @if ($tab === 'active')
                    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Add product</a>
                @endif
            </div>
        @else
            <p class="admin-result-count">
                Showing {{ $products->firstItem() }}–{{ $products->lastItem() }}
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th></th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Variants</th>
                            <th>Weight</th>
                            <th>GTIN</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td><strong>{{ $product->id }}</strong></td>
                                <td>
                                    <a href="{{ route('admin.products.edit', $product) }}">
                                        <img
                                            class="admin-product-thumb"
                                            src="{{ $product->imageUrl() }}"
                                            alt="{{ $product->name['fr'] ?? $product->localizedName() }}"
                                            loading="lazy"
                                        >
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="admin-table-strong admin-table-truncate" title="{{ $product->name['fr'] ?? $product->localizedName() }}">
                                        {{ $product->name['fr'] ?? $product->localizedName() }}
                                    </a>
                                    @if (filled($product->sku))
                                        <span class="admin-table-sub">{{ $product->sku }}</span>
                                    @endif
                                </td>
                                <td>{{ $product->category?->name['fr'] ?? '—' }}</td>
                                <td>{{ $product->formattedPrice() }}</td>
                                <td>{{ $product->quantity }}</td>
                                <td>{{ $product->variants_count > 0 ? $product->variants_count : '—' }}</td>
                                <td>{{ $product->weight_grams ? number_format($product->weight_grams).' g' : '—' }}</td>
                                <td>{{ filled($product->gtin) ? $product->gtin : '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.products.status', $product) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="badge badge-btn {{ $product->is_active ? 'badge-active' : 'badge-disabled' }}"
                                            title="Click to {{ $product->is_active ? 'disable' : 'activate' }}"
                                        >
                                            {{ $product->is_active ? 'Active' : 'Disabled' }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">Edit</a>
                                        <a href="{{ localized_route('products.show', ['product' => $product->slug], 'fr') }}" class="btn btn-sm btn-secondary" target="_blank" rel="noopener noreferrer">View</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $products])
        @endif
    </div>
@endsection
