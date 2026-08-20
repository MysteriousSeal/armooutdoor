@extends('layouts.admin')

@section('title', 'Messages')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Admin</p>
            <h2 class="admin-list-title">Messages</h2>
            <p class="admin-list-lede">Contact form submissions from the storefront.</p>
        </header>

        <div class="admin-stat-grid">
            <div class="admin-stat-card">
                <span class="admin-stat-label">Total messages</span>
                <span class="admin-stat-value">{{ number_format($totalCount) }}</span>
            </div>
            <div class="admin-stat-card {{ $unreadCount > 0 ? 'admin-stat-card--warning' : '' }}">
                <span class="admin-stat-label">Unread</span>
                <span class="admin-stat-value">{{ number_format($unreadCount) }}</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-label">Last 7 days</span>
                <span class="admin-stat-value">{{ number_format($thisWeekCount) }}</span>
            </div>
        </div>

        @if ($messages->isEmpty())
            <p class="empty-state">No messages yet.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>From</th>
                            <th>Subject</th>
                            <th>Order</th>
                            <th>Received</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $message)
                            <tr class="{{ $message->isRead() ? '' : 'admin-message-row--unread' }}">
                                <td>
                                    @unless ($message->isRead())
                                        <span class="admin-message-dot" title="Unread" aria-label="Unread"></span>
                                    @endunless
                                </td>
                                <td>
                                    <div class="admin-customer-cell">
                                        <span class="admin-customer-avatar" aria-hidden="true">{{ $message->initials() }}</span>
                                        <span class="admin-customer-identity">
                                            @if ($message->user?->isAdmin())
                                                <span class="admin-message-sender">
                                                    <span class="admin-table-primary">{{ $message->name }}</span>
                                                    <span class="admin-role-chip">Admin</span>
                                                </span>
                                            @elseif ($message->user)
                                                <a href="{{ route('admin.customers.show', $message->user) }}" class="admin-table-link">{{ $message->name }}</a>
                                            @else
                                                <span class="admin-table-primary">{{ $message->name }}</span>
                                                @php $possibleCustomer = $possibleCustomersByEmail->get(mb_strtolower($message->email)); @endphp
                                                @if ($possibleCustomer)
                                                    <a href="{{ route('admin.customers.show', $possibleCustomer) }}" class="admin-message-guess">possibly {{ $possibleCustomer->name }}</a>
                                                @endif
                                            @endif
                                            <span class="admin-table-sub">{{ $message->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ $message->subject }}</span>
                                    <span class="admin-message-snippet">{{ \Illuminate\Support\Str::limit($message->message, 60) }}</span>
                                </td>
                                <td>
                                    @if ($message->order)
                                        <a href="{{ route('admin.orders.show', $message->order) }}" class="admin-table-link">{{ $message->order->number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ $message->created_at->format('d M Y') }}</span>
                                    <span class="admin-table-sub">{{ $message->created_at->format('H:i') }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.messages.show', $message) }}" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $messages])
        @endif
    </div>
@endsection
