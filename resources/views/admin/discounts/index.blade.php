@extends('layouts.admin')

@section('title', 'Discounts')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">Discounts</h2>
                    <p class="admin-list-lede">
                        @if ($tab === 'codes')
                            Coupon codes customers enter at checkout, taken off the cart total.
                        @else
                            A reduced price on a single product — no coupon code needed. The sale price shows everywhere automatically.
                        @endif
                    </p>
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
                Discount codes <span class="admin-tab-count">{{ number_format($discountCodeCount) }}</span>
            </a>
        </nav>

        @if ($tab === 'codes')
            <nav class="admin-subtabs" aria-label="Discount code status">
                <a href="{{ route('admin.discounts.index', ['tab' => 'codes', 'code_status' => 'active']) }}" class="{{ $codeStatus === 'active' ? 'active' : '' }}">
                    Active <span class="admin-tab-count">{{ number_format($activeCodesCount) }}</span>
                </a>
                <a href="{{ route('admin.discounts.index', ['tab' => 'codes', 'code_status' => 'expired']) }}" class="{{ $codeStatus === 'expired' ? 'active' : '' }}">
                    Expired <span class="admin-tab-count">{{ number_format($expiredCodesCount) }}</span>
                </a>
                <a href="{{ route('admin.discounts.index', ['tab' => 'codes', 'code_status' => 'sold_out']) }}" class="{{ $codeStatus === 'sold_out' ? 'active' : '' }}">
                    No usage remaining <span class="admin-tab-count">{{ number_format($soldOutCodesCount) }}</span>
                </a>
            </nav>
        @endif

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
                                @if (auth()->user()->isOwner())
                                    <button type="button" class="btn btn-sm btn-secondary" data-modal-open="discount-delete-{{ $discount->id }}">Remove</button>
                                    <dialog id="discount-delete-{{ $discount->id }}" class="modal" aria-labelledby="discount-delete-{{ $discount->id }}-title">
                                        <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}">
                                            @csrf
                                            @method('DELETE')
                                            <p class="modal-kicker">{{ $discount->name }}</p>
                                            <h3 class="modal-title" id="discount-delete-{{ $discount->id }}-title">Remove this discount?</h3>
                                            <p class="modal-body">This can't be undone. Orders that already used it keep their applied discount.</p>
                                            <div class="modal-actions">
                                                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                                                <button type="submit" class="btn btn-primary">Remove discount</button>
                                            </div>
                                        </form>
                                    </dialog>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        @else
            @if ($discountCodeCount === 0)
                <div class="empty-state">
                    <p>No discount codes yet.</p>
                    <a href="{{ route('admin.discount-codes.create') }}" class="btn btn-primary">Add code</a>
                </div>
            @elseif ($discountCodes->isEmpty())
                <div class="empty-state">
                    <p>
                        @switch($codeStatus)
                            @case('expired')
                                No expired codes.
                                @break
                            @case('sold_out')
                                No codes with usage remaining.
                                @break
                            @default
                                No active codes.
                        @endswitch
                    </p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table admin-discount-codes-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th>Customer</th>
                                <th>Uses</th>
                                <th>Per customer</th>
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
                                    <td>
                                        <span class="order-discount-badge">{{ $discountCode->label() }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $discountCode->status() === 'active' ? 'badge-active' : ($discountCode->status() === 'sold_out' ? 'badge-placed' : 'badge-disabled') }}">
                                            {{ $discountCode->statusLabel() }}
                                        </span>
                                    </td>
                                    <td>{{ $discountCode->remainingLabel() }}</td>
                                    <td>
                                        <span class="admin-table-primary">{{ $discountCode->user?->name ?? 'Any customer' }}</span>
                                        @if ($discountCode->user?->email)
                                            <span class="admin-table-sub">{{ $discountCode->user->email }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $discountCode->hasLimitedQuantity() ? $discountCode->quantity.' remaining' : 'Unlimited' }}</td>
                                    <td>{{ $discountCode->hasMaxUsesPerCustomer() ? $discountCode->max_uses_per_customer : 'Unlimited' }}</td>
                                    <td>
                                        <div class="admin-table-actions">
                                            <a href="{{ route('admin.discount-codes.edit', $discountCode) }}" class="btn btn-sm btn-secondary">Edit</a>
                                            @if (auth()->user()->isOwner())
                                                <button type="button" class="btn btn-sm btn-secondary" data-modal-open="discount-code-delete-{{ $discountCode->id }}">Remove</button>
                                                <dialog id="discount-code-delete-{{ $discountCode->id }}" class="modal" aria-labelledby="discount-code-delete-{{ $discountCode->id }}-title">
                                                    <form method="POST" action="{{ route('admin.discount-codes.destroy', $discountCode) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <p class="modal-kicker">{{ $discountCode->code }}</p>
                                                        <h3 class="modal-title" id="discount-code-delete-{{ $discountCode->id }}-title">Remove this discount code?</h3>
                                                        <p class="modal-body">This can't be undone. Customers who already used it keep their applied discount.</p>
                                                        <div class="modal-actions">
                                                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Remove code</button>
                                                        </div>
                                                    </form>
                                                </dialog>
                                            @endif
                                        </div>
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
