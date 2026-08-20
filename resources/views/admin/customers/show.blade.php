@extends('layouts.admin')

@php
    $displayName = $customer->name !== '' ? $customer->name : $customer->email;
    $lastOrder = $orders->first();
@endphp

@section('title', $displayName)

@section('content')
    <div class="admin-list-page admin-customer-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.customers.index') }}">Customers</a></p>
                    <div class="admin-customer-hero">
                        <span class="admin-customer-avatar admin-customer-avatar--lg" aria-hidden="true">{{ $customer->initials() }}</span>
                        <div>
                            <div class="admin-customer-heading">
                                <h2 class="admin-list-title">{{ $displayName }}</h2>
                                @if ($customer->external)
                                    <span class="badge badge-placed">External</span>
                                @endif
                            </div>
                            <p class="admin-list-lede">
                                @if ($customer->email)
                                    <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                                @else
                                    No email
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($orders->count()) }} {{ \Illuminate\Support\Str::plural('order', $orders->count()) }}</span>
                <span class="admin-list-chip">{{ format_euros($spentCents) }} spent</span>
                @if ($testOrderCount > 0)
                    <span class="admin-list-chip admin-list-chip--muted">
                        excluding {{ $testOrderCount }} test {{ \Illuminate\Support\Str::plural('order', $testOrderCount) }}
                    </span>
                @endif
                @if ($averageOrderCents > 0)
                    <span class="admin-list-chip">Avg. {{ format_euros($averageOrderCents) }}</span>
                @endif
                <span class="admin-list-chip">{{ number_format($customer->addresses->count()) }} {{ \Illuminate\Support\Str::plural('address', $customer->addresses->count()) }}</span>
                <span class="admin-list-chip">{{ number_format($discountCodes->count()) }} {{ \Illuminate\Support\Str::plural('code', $discountCodes->count()) }}</span>
                <span class="admin-list-chip">Joined {{ $customer->created_at->format('d M Y') }}</span>
            </div>
        </header>

        <div class="admin-order-layout">
            <div class="order-main">
                <section class="order-panel">
                    <div class="admin-customer-section-head">
                        <h3 class="order-panel-title">Orders</h3>
                        <span class="admin-tab-count">{{ number_format($orders->count()) }}</span>
                    </div>
                    @if ($orders->isEmpty())
                        <p class="empty-state">No orders yet.</p>
                    @else
                        <ul class="admin-customer-orders">
                            @foreach ($orders as $order)
                                <li>
                                    <div class="admin-dash-list-main">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">{{ $order->number }}</a>
                                        <span class="admin-table-sub">
                                            {{ $order->created_at->format('d M Y · H:i') }}
                                            · {{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }}
                                            @if ($order->carrierName() !== '')
                                                · {{ $order->carrierName() }}
                                            @endif
                                        </span>
                                    </div>
                                    @if ($order->isArchived())
                                        <span class="badge badge-disabled" title="Archived, and still counted in the total spent">Archived</span>
                                    @endif
                                    @if ($order->isTest())
                                        <span class="badge badge-test" title="Left out of the total spent">Test</span>
                                    @endif
                                    <span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                                    <span class="admin-dash-list-value">{{ $order->formattedTotal() }}</span>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">View</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="order-panel">
                    <div class="admin-customer-section-head">
                        <h3 class="order-panel-title">Discount codes</h3>
                        <span class="admin-tab-count">{{ number_format($discountCodes->count()) }}</span>
                        <a href="{{ route('admin.discount-codes.create', ['user_id' => $customer->id]) }}" class="btn btn-sm btn-secondary">Add code</a>
                    </div>
                    @if ($discountCodes->isEmpty())
                        <p class="empty-state">No codes reserved for this customer.</p>
                    @else
                        <ul class="admin-customer-codes">
                            @foreach ($discountCodes as $discountCode)
                                @php
                                    $codeStatus = $discountCode->status();
                                @endphp
                                <li>
                                    <div class="admin-dash-list-main">
                                        <div class="admin-customer-code-name">
                                            <a href="{{ route('admin.discount-codes.edit', $discountCode) }}" class="admin-table-strong">{{ $discountCode->code }}</a>
                                            <button type="button" class="admin-copy-code" data-copy-code="{{ $discountCode->code }}" title="Copy code" aria-label="Copy code">
                                                <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                                                    <rect x="9" y="9" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/>
                                                    <path d="M6 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <span class="admin-table-sub">
                                            {{ $discountCode->remainingLabel() }}
                                            · {{ $discountCode->orders_count }} {{ \Illuminate\Support\Str::plural('use', $discountCode->orders_count) }}
                                            @if ($discountCode->hasLimitedQuantity())
                                                · {{ $discountCode->quantity }} remaining
                                            @endif
                                            @if ($discountCode->hasMaxUsesPerCustomer())
                                                · max {{ $discountCode->max_uses_per_customer }} per customer
                                            @endif
                                        </span>
                                    </div>
                                    <span class="order-discount-badge">{{ $discountCode->label() }}</span>
                                    <span class="badge {{ $codeStatus === 'active' ? 'badge-active' : ($codeStatus === 'sold_out' ? 'badge-placed' : 'badge-disabled') }}">
                                        {{ $discountCode->statusLabel() }}
                                    </span>
                                    <a href="{{ route('admin.discount-codes.edit', $discountCode) }}" class="btn btn-sm btn-secondary">Edit</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">Account</h3>
                    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="admin-customer-account-form">
                        @csrf
                        @method('PATCH')
                        <div class="admin-customer-account-row">
                            <div class="form-group">
                                <label for="first_name">First name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name', $customer->first_name) }}" maxlength="80" required>
                                @error('first_name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name', $customer->last_name) }}" maxlength="80" required>
                                @error('last_name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}" maxlength="255" required>
                            @error('email') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="btn btn-secondary">Save account details</button>
                    </form>

                    <form method="POST" action="{{ route('admin.customers.send-reset-link', $customer) }}" class="admin-customer-reset-form">
                        @csrf
                        <p class="admin-customer-reset-hint">Emails a password reset link. Nobody at Armo Outdoor sees or sets the password directly.</p>
                        <button type="submit" class="btn btn-secondary">Send password reset link</button>
                    </form>

                    <dl class="admin-customer-facts admin-customer-facts--readonly">
                        <div>
                            <dt>Joined</dt>
                            <dd>{{ $customer->created_at->format('d M Y · H:i') }}</dd>
                        </div>
                        <div>
                            <dt>Last order</dt>
                            <dd>
                                @if ($lastOrder)
                                    <a href="{{ route('admin.orders.show', $lastOrder) }}">{{ $lastOrder->created_at->format('d M Y') }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Wishlist</dt>
                            <dd>{{ number_format($wishlistCount) }} {{ \Illuminate\Support\Str::plural('item', $wishlistCount) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Addresses</h3>
                    @if ($customer->addresses->isEmpty())
                        <p>No saved addresses.</p>
                    @else
                        <div class="admin-customer-addresses">
                            @foreach ($customer->addresses as $address)
                                <article class="customer-address">
                                    <div class="customer-address-head">
                                        <p class="admin-table-primary">{{ $address->recipientName() !== '' ? $address->recipientName() : 'Address' }}</p>
                                        @if ($address->is_default)
                                            <span class="badge badge-active">Default</span>
                                        @endif
                                    </div>
                                    @if ($address->label)
                                        <p class="admin-table-sub">{{ $address->label }}</p>
                                    @endif
                                    <p class="customer-address-lines">
                                        {{ $address->line1 }}<br>
                                        @if ($address->line2)
                                            {{ $address->line2 }}<br>
                                        @endif
                                        {{ $address->postal_code }} {{ $address->city }}<br>
                                        {{ $address->countryName() }}
                                    </p>
                                    @if ($address->phone)
                                        <p class="customer-address-phone">
                                            <a href="tel:{{ $address->phone }}">{{ $address->phone }}</a>
                                        </p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Notes</h3>
                    <p class="admin-customer-notes-hint">Only visible to admins.</p>
                    <form method="POST" action="{{ route('admin.customers.notes.update', $customer) }}" class="admin-customer-notes">
                        @csrf
                        @method('PATCH')
                        <textarea
                            name="notes"
                            class="form-control"
                            rows="5"
                            placeholder="Internal notes about this customer…"
                        >{{ old('notes', $customer->notes) }}</textarea>
                        @error('notes')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="btn btn-secondary">Save notes</button>
                    </form>
                </section>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-copy-code.js') }}" defer></script>
@endpush
