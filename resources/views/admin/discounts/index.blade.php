@extends('layouts.admin')

@section('title', 'Discounts')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">Discounts</h2>
                    <p class="admin-list-lede">A reduced price on a single product — no coupon code needed. The sale price shows everywhere automatically.</p>
                </div>
                @if ($tab === 'products')
                    <a href="{{ route('admin.discounts.create') }}" class="btn btn-primary">Add discount</a>
                @else
                    <a href="{{ route('admin.discount-codes.create') }}" class="btn btn-primary">Add code</a>
                @endif
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Discount tabs">
            <a href="{{ route('admin.discounts.index', ['tab' => 'products', 'status' => $status]) }}" class="{{ $tab === 'products' ? 'active' : '' }}">
                Product discounts <span class="admin-tab-count">{{ number_format($discountCount) }}</span>
            </a>
            <a href="{{ route('admin.discounts.index', ['tab' => 'codes']) }}" class="{{ $tab === 'codes' ? 'active' : '' }}">
                Discount codes <span class="admin-tab-count">{{ number_format($discountCodes->count()) }}</span>
            </a>
        </nav>

        @if ($tab === 'products')
            <nav class="admin-subtabs" aria-label="Discount status">
                <a href="{{ route('admin.discounts.index', ['tab' => 'products', 'status' => 'active']) }}" class="{{ $status === 'active' ? 'active' : '' }}">
                    Active <span class="admin-tab-count">{{ number_format($activeCount) }}</span>
                </a>
                <a href="{{ route('admin.discounts.index', ['tab' => 'products', 'status' => 'scheduled']) }}" class="{{ $status === 'scheduled' ? 'active' : '' }}">
                    Scheduled <span class="admin-tab-count">{{ number_format($scheduledCount) }}</span>
                </a>
                <a href="{{ route('admin.discounts.index', ['tab' => 'products', 'status' => 'expired']) }}" class="{{ $status === 'expired' ? 'active' : '' }}">
                    Expired <span class="admin-tab-count">{{ number_format($expiredCount) }}</span>
                </a>
            </nav>

            @if ($discountCount === 0)
                <div class="empty-state">
                    <p>No product discounts yet.</p>
                    <a href="{{ route('admin.discounts.create') }}" class="btn btn-primary">Add discount</a>
                </div>
            @elseif ($discounts->isEmpty())
                <div class="empty-state">
                    <p>
                        @switch($status)
                            @case('scheduled')
                                No scheduled discounts.
                                @break
                            @case('expired')
                                No expired discounts.
                                @break
                            @default
                                No active discounts.
                        @endswitch
                    </p>
                </div>
            @else
                <ul class="admin-discount-list">
                    @foreach ($discounts as $discount)
                        @php
                            $product = $discount->product;
                            $status = $discount->status();
                        @endphp
                        <li class="admin-discount-card">
                            @if ($product)
                                <a href="{{ route('admin.products.edit', $product) }}" class="admin-discount-media">
                                    <img
                                        src="{{ $product->imageUrl() }}"
                                        alt=""
                                        width="72"
                                        height="72"
                                        loading="lazy"
                                    >
                                </a>
                            @else
                                <span class="admin-discount-media is-empty"></span>
                            @endif

                            <div class="admin-discount-main">
                                <p class="admin-discount-name">
                                    @if ($product)
                                        <a href="{{ route('admin.products.edit', $product) }}">
                                            {{ $product->name['fr'] ?? $product->localizedName() }}
                                        </a>
                                    @else
                                        Deleted product
                                    @endif
                                </p>
                                @if ($product?->sku)
                                    <p class="admin-discount-sku">SKU {{ $product->sku }}</p>
                                @endif
                                <p class="admin-discount-price">
                                    @if ($product)
                                        <span>{{ $product->formattedOriginalPrice() }}</span>
                                        <span class="admin-discount-price-sep" aria-hidden="true">→</span>
                                        <span>{{ format_euros($discount->apply($product->price_cents)) }}</span>
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>

                            <div class="admin-discount-offer">
                                <span class="order-discount-badge">{{ $discount->label() }}</span>
                                <span class="admin-discount-offer-type">{{ $discount->typeLabel() }}</span>
                            </div>

                            <div class="admin-discount-schedule">
                                <span class="badge {{ $status === 'active' ? 'badge-active' : ($status === 'scheduled' ? 'badge-placed' : 'badge-disabled') }}">
                                    {{ $discount->statusLabel() }}
                                </span>
                                <p class="admin-discount-remaining">{{ $discount->remainingLabel() }}</p>
                                <p class="admin-discount-window">
                                    @if ($discount->starts_at || $discount->ends_at)
                                        {{ $discount->formattedStartsAt() ?? 'No start date' }}
                                        <span aria-hidden="true">→</span>
                                        {{ $discount->formattedEndsAt() ?? 'No end date' }}
                                    @else
                                        Always on
                                    @endif
                                </p>
                            </div>

                            <div class="admin-discount-actions">
                                <a href="{{ route('admin.discounts.edit', $discount) }}" class="btn btn-sm btn-secondary">Edit</a>
                                <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-secondary">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        @else
            @if ($discountCodes->isEmpty())
                <div class="empty-state">
                    <p>No discount codes yet.</p>
                    <a href="{{ route('admin.discount-codes.create') }}" class="btn btn-primary">Add code</a>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Customer</th>
                                <th>Quantity available</th>
                                <th>Max per customer</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($discountCodes as $discountCode)
                                <tr>
                                    <td>{{ $discountCode->id }}</td>
                                    <td>
                                        <a href="{{ route('admin.discount-codes.edit', $discountCode) }}" class="admin-table-strong">{{ $discountCode->code }}</a>
                                        <button type="button" class="admin-copy-code" data-copy-code="{{ $discountCode->code }}" title="Copy code" aria-label="Copy code">
                                            <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                                                <rect x="9" y="9" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/>
                                                <path d="M6 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    </td>
                                    <td>{{ $discountCode->label() }} <span class="admin-table-sub">off cart total</span></td>
                                    <td>
                                        @if ($discountCode->user)
                                            {{ $discountCode->user->name }}
                                        @else
                                            <span class="admin-table-sub">Any customer</span>
                                        @endif
                                    </td>
                                    <td>{{ $discountCode->quantityLabel() }}</td>
                                    <td>{{ $discountCode->maxUsesPerCustomerLabel() }}</td>
                                    <td>
                                        <a href="{{ route('admin.discount-codes.edit', $discountCode) }}" class="btn btn-sm btn-secondary">Edit</a>
                                        <form method="POST" action="{{ route('admin.discount-codes.destroy', $discountCode) }}" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-secondary">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-copy-code.js') }}" defer></script>
@endpush
