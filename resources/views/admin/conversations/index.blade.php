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

        <nav class="admin-tabs" aria-label="Conversation status">
            <a href="{{ route('admin.conversations.index') }}" class="{{ $tab === 'open' ? 'active' : '' }}">
                Open <span class="admin-tab-count">{{ number_format($openCount) }}</span>
            </a>
            <a href="{{ route('admin.conversations.index', ['tab' => 'closed']) }}" class="{{ $tab === 'closed' ? 'active' : '' }}">
                Closed <span class="admin-tab-count">{{ number_format($closedCount) }}</span>
            </a>
            <a href="{{ route('admin.conversations.index', ['tab' => 'all']) }}" class="{{ $tab === 'all' ? 'active' : '' }}">
                All <span class="admin-tab-count">{{ number_format($totalCount) }}</span>
            </a>
        </nav>

        @if ($conversations->isEmpty())
            <p class="empty-state">
                @if ($tab === 'closed')
                    No closed conversations.
                @elseif ($tab === 'open')
                    Nothing waiting for a reply.
                @else
                    No messages yet.
                @endif
            </p>
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
                        @foreach ($conversations as $conversation)
                            <tr class="{{ $conversation->hasUnreadForAdmin() ? 'admin-message-row--unread' : '' }}">
                                <td>
                                    @if ($conversation->hasUnreadForAdmin())
                                        <span class="admin-message-dot" title="Unread" aria-label="Unread"></span>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-customer-cell">
                                        <span class="admin-customer-avatar" aria-hidden="true">{{ $conversation->initials() }}</span>
                                        <span class="admin-customer-identity">
                                            @if ($conversation->user?->isAdmin())
                                                <span class="admin-message-sender">
                                                    <span class="admin-table-primary">{{ $conversation->name }}</span>
                                                    <span class="admin-role-chip">Admin</span>
                                                </span>
                                            @elseif ($conversation->user)
                                                <a href="{{ route('admin.customers.show', $conversation->user) }}" class="admin-table-link">{{ $conversation->name }}</a>
                                            @else
                                                <span class="admin-table-primary">{{ $conversation->name }}</span>
                                                @php $possibleCustomer = $possibleCustomersByEmail->get(mb_strtolower($conversation->email)); @endphp
                                                @if ($possibleCustomer)
                                                    <a href="{{ route('admin.customers.show', $possibleCustomer) }}" class="admin-message-guess">possibly {{ $possibleCustomer->name }}</a>
                                                @endif
                                            @endif
                                            <span class="admin-table-sub">{{ $conversation->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-message-sender">
                                        <span class="admin-table-primary">{{ $conversation->subject }}</span>
                                        @if ($conversation->messages_count > 1)
                                            <span class="admin-thread-count" title="{{ $conversation->messages_count }} messages">{{ $conversation->messages_count }}</span>
                                        @endif
                                        @if ($conversation->isClosed())
                                            <span class="admin-status-chip admin-status-chip--closed">Closed</span>
                                        @endif
                                    </span>
                                    <span class="admin-message-snippet">
                                        @if ($conversation->latestMessage?->isFromAdmin())
                                            <span class="admin-message-snippet-tag">You</span>
                                        @endif
                                        {{ \Illuminate\Support\Str::limit($conversation->latestMessage?->body, 60) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($conversation->order)
                                        <a href="{{ route('admin.orders.show', $conversation->order) }}" class="admin-table-link">{{ $conversation->order->number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ $conversation->created_at->format('d M Y') }}</span>
                                    <span class="admin-table-sub">{{ $conversation->created_at->format('H:i') }}</span>
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.conversations.show', $conversation) }}" class="btn btn-sm btn-primary">View</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $conversations])
        @endif
    </div>
@endsection
