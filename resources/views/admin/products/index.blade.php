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
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.products.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
                    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Add product</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($productCount) }} products</span>
                <span class="admin-list-chip">{{ number_format($disabledCount) }} disabled</span>
                <span class="admin-list-chip">{{ number_format($outOfStockCount) }} out of stock</span>
                <span class="admin-list-chip">{{ number_format($noSkuCount) }} without SKU</span>
                <span class="admin-list-chip">{{ number_format($noGtinCount) }} without GTIN</span>
                <span class="admin-list-chip">{{ number_format($noWeightCount) }} without weight</span>
                @if ($search !== '' || $categorySlug !== '')
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        @php
            $listQuery = fn (array $overrides = []) => array_filter([
                'tab' => $overrides['tab'] ?? $tab,
                'sort' => ($overrides['sort'] ?? $sort) !== 'id-asc' ? ($overrides['sort'] ?? $sort) : null,
                'search' => array_key_exists('search', $overrides) ? $overrides['search'] : ($search !== '' ? $search : null),
                'category' => array_key_exists('category', $overrides) ? $overrides['category'] : ($categorySlug !== '' ? $categorySlug : null),
            ]);
            $tabQuery = fn (string $name) => $listQuery(['tab' => $name]);
            $sortLink = function (string $column) use ($sort, $listQuery): array {
                $next = $sort === $column.'-asc' ? $column.'-desc' : $column.'-asc';

                return [
                    'url' => route('admin.products.index', $listQuery(['sort' => $next])),
                    'state' => str_starts_with($sort, $column.'-') ? substr($sort, strlen($column) + 1) : null,
                ];
            };
            $hasFilters = $search !== '' || $categorySlug !== '';
            $activeCategory = $categorySlug !== ''
                ? $categories->firstWhere('slug', $categorySlug)
                : null;
        @endphp

        <nav class="admin-tabs" aria-label="Product tabs">
            <a href="{{ route('admin.products.index', $tabQuery('active')) }}" class="{{ $tab === 'active' ? 'active' : '' }}">
                Products <span class="admin-tab-count">{{ number_format($activeCount) }}</span>
            </a>
            <a href="{{ route('admin.products.index', $tabQuery('out-of-stock')) }}" class="{{ $tab === 'out-of-stock' ? 'active' : '' }}">
                Out of stock <span class="admin-tab-count">{{ number_format($outOfStockCount) }}</span>
            </a>
            <a href="{{ route('admin.products.index', $tabQuery('no-sku')) }}" class="{{ $tab === 'no-sku' ? 'active' : '' }}">
                Missing SKU <span class="admin-tab-count">{{ number_format($noSkuCount) }}</span>
            </a>
            <a href="{{ route('admin.products.index', $tabQuery('no-gtin')) }}" class="{{ $tab === 'no-gtin' ? 'active' : '' }}">
                Missing GTIN <span class="admin-tab-count">{{ number_format($noGtinCount) }}</span>
            </a>
            <a href="{{ route('admin.products.index', $tabQuery('no-weight')) }}" class="{{ $tab === 'no-weight' ? 'active' : '' }}">
                Missing weight <span class="admin-tab-count">{{ number_format($noWeightCount) }}</span>
            </a>
            <a href="{{ route('admin.products.index', $tabQuery('disabled')) }}" class="{{ $tab === 'disabled' ? 'active' : '' }}">
                Disabled <span class="admin-tab-count">{{ number_format($disabledCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.products.index') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($sort !== 'id-asc')
                <input type="hidden" name="sort" value="{{ $sort }}">
            @endif

            <div class="admin-filter-row admin-filter-row--products">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-filter-label" for="product-search">Search</label>
                    <input
                        id="product-search"
                        type="search"
                        name="search"
                        class="form-control admin-toolbar-search"
                        placeholder="Name or slug…"
                        value="{{ $search }}"
                    >
                </div>
                <div class="admin-filter-field">
                    <label class="admin-filter-label" for="product-category">Category</label>
                    <select id="product-category" name="category" class="form-control">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected($categorySlug === $category->slug)>
                                @if ($category->parent_id)
                                    — {{ $category->name['fr'] ?? $category->localizedName() }}
                                @else
                                    {{ $category->name['fr'] ?? $category->localizedName() }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if ($hasFilters)
                        <a href="{{ route('admin.products.index', $listQuery(['search' => null, 'category' => null])) }}" class="btn btn-secondary">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($hasFilters)
            <div class="admin-filter-chips" aria-label="Active filters">
                @if ($search !== '')
                    <a href="{{ route('admin.products.index', $listQuery(['search' => null])) }}" class="admin-filter-chip">
                        Search · {{ $search }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($activeCategory)
                    <a href="{{ route('admin.products.index', $listQuery(['category' => null])) }}" class="admin-filter-chip">
                        Category · {{ $activeCategory->name['fr'] ?? $activeCategory->localizedName() }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
            </div>
        @endif

        @if ($products->isEmpty())
            <div class="empty-state">
                <p>
                    @if ($hasFilters)
                        No products match these filters.
                    @else
                        @switch($tab)
                            @case('disabled')
                                No disabled products.
                                @break
                            @case('out-of-stock')
                                No out of stock products.
                                @break
                            @case('no-sku')
                                No products missing a SKU.
                                @break
                            @case('no-gtin')
                                No products missing a GTIN.
                                @break
                            @case('no-weight')
                                No products missing a weight.
                                @break
                            @default
                                No products found.
                        @endswitch
                    @endif
                </p>
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
                            @php
                                $idSort = $sortLink('id');
                                $nameSort = $sortLink('name');
                                $stockSort = $sortLink('stock');
                            @endphp
                            <th>
                                <a href="{{ $idSort['url'] }}" class="admin-sort-link {{ $idSort['state'] ? 'is-active' : '' }}">
                                    ID
                                    <span class="admin-sort-dir" aria-hidden="true">{{ $idSort['state'] === 'desc' ? '↓' : '↑' }}</span>
                                </a>
                            </th>
                            <th></th>
                            <th>
                                <a href="{{ $nameSort['url'] }}" class="admin-sort-link {{ $nameSort['state'] ? 'is-active' : '' }}">
                                    Name
                                    @if ($nameSort['state'])
                                        <span class="admin-sort-dir" aria-hidden="true">{{ $nameSort['state'] === 'desc' ? '↓' : '↑' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>
                                <a href="{{ $stockSort['url'] }}" class="admin-sort-link {{ $stockSort['state'] ? 'is-active' : '' }}">
                                    Stock
                                    @if ($stockSort['state'])
                                        <span class="admin-sort-dir" aria-hidden="true">{{ $stockSort['state'] === 'desc' ? '↓' : '↑' }}</span>
                                    @endif
                                </a>
                            </th>
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
