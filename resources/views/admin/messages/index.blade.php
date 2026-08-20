@extends('layouts.admin')

@section('title', 'Messages')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Admin</p>
            <h2 class="admin-list-title">Messages</h2>
            <p class="admin-list-lede">Contact form submissions from the storefront.</p>
        </header>

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
                                    <span class="admin-table-primary">{{ $message->name }}</span>
                                    <span class="admin-table-sub">{{ $message->email }}</span>
                                </td>
                                <td>{{ $message->subject }}</td>
                                <td>
                                    @if ($message->order)
                                        <a href="{{ route('admin.orders.show', $message->order) }}">{{ $message->order->number }}</a>
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
