@extends('layouts.admin')

@section('title', 'Activity')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Admin</p>
            <h2 class="admin-list-title">Activity</h2>
            <p class="admin-list-lede">A record of admin actions across orders, products, discounts, settings and admin accounts.</p>
        </header>

        @if ($logs->isEmpty())
            <p class="empty-state">Nothing logged yet.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Admin</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $subjectRoute = match (true) {
                                    $log->subject instanceof \App\Models\Order => route('admin.orders.show', $log->subject),
                                    $log->subject instanceof \App\Models\Product => route('admin.products.edit', $log->subject),
                                    $log->subject instanceof \App\Models\Discount => route('admin.discounts.edit', $log->subject),
                                    $log->subject instanceof \App\Models\DiscountCode => route('admin.discount-codes.edit', $log->subject),
                                    $log->subject instanceof \App\Models\Supplier => route('admin.settings.suppliers.edit', $log->subject),
                                    $log->subject instanceof \App\Models\Marketplace => route('admin.settings.orders.edit'),
                                    $log->subject instanceof \App\Models\PackageType, $log->subject instanceof \App\Models\Carrier => route('admin.settings.shipping.edit'),
                                    $log->subject instanceof \App\Models\User => auth()->user()->isOwner() ? route('admin.settings.admins.edit', $log->subject) : null,
                                    default => null,
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="admin-table-primary">{{ $log->created_at->format('d M Y') }}</span>
                                    <span class="admin-table-sub">{{ $log->created_at->format('H:i') }}</span>
                                </td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td><span class="admin-list-chip">{{ $log->subjectLabel() }}</span></td>
                                <td>{{ $log->description }}</td>
                                <td>
                                    @if ($subjectRoute)
                                        <a href="{{ $subjectRoute }}" class="btn btn-sm btn-primary">View</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $logs])
        @endif
    </div>
@endsection
