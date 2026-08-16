@extends('layouts.admin')

@section('title', 'Discounts')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">Discounts</h2>
                    <p class="admin-list-lede">A simple discount for a single product, no code needed — the reduced price shows everywhere automatically.</p>
                </div>
                <a href="{{ route('admin.discounts.create') }}" class="btn btn-primary">Add discount</a>
            </div>
        </header>

        @if ($discounts->isEmpty())
            <div class="empty-state">
                <p>No discounts yet.</p>
                <a href="{{ route('admin.discounts.create') }}" class="btn btn-primary">Add discount</a>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Discount</th>
                            <th>Price</th>
                            <th>Window</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($discounts as $discount)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.products.edit', $discount->product) }}">
                                        {{ $discount->product->name['fr'] ?? $discount->product->localizedName() }}
                                    </a>
                                </td>
                                <td>{{ $discount->label() }}</td>
                                <td>
                                    @if ($discount->isActive())
                                        <span class="card-price-original">{{ $discount->product->formattedOriginalPrice() }}</span>
                                    @endif
                                    {{ $discount->product->formattedPrice() }}
                                </td>
                                <td>
                                    {{ $discount->starts_at?->format('d/m/Y H:i') ?? '—' }} → {{ $discount->ends_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td>
                                    @if ($discount->isActive())
                                        <span class="badge badge-active">Active</span>
                                    @elseif ($discount->starts_at?->isFuture())
                                        <span class="badge badge-placed">Scheduled</span>
                                    @else
                                        <span class="badge badge-disabled">Expired</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.discounts.edit', $discount) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}" style="display: inline;">
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
    </div>
@endsection
