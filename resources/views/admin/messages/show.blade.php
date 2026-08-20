@extends('layouts.admin')

@section('title', $message->subject)

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker"><a href="{{ route('admin.messages.index') }}">Messages</a></p>
            <h2 class="admin-list-title">{{ $message->subject }}</h2>
            <p class="admin-list-lede">
                From {{ $message->name }} ({{ $message->email }})
                · {{ $message->created_at->format('d M Y · H:i') }}
            </p>
            @if ($message->user || $message->order || $possibleCustomer)
                <div class="admin-message-links">
                    @if ($message->user?->isAdmin())
                        <span class="admin-message-link-chip admin-message-link-chip--admin">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M12 3 4.5 6v5.5c0 4.3 3.1 8 7.5 9 4.4-1 7.5-4.7 7.5-9V6L12 3Z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Admin
                        </span>
                    @elseif ($message->user)
                        <a href="{{ route('admin.customers.show', $message->user) }}" class="admin-message-link-chip">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm-7 9a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Customer account
                        </a>
                    @elseif ($possibleCustomer)
                        <a href="{{ route('admin.customers.show', $possibleCustomer) }}" class="admin-message-link-chip admin-message-link-chip--guess">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm-7 9a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Possibly {{ $possibleCustomer->name }}
                        </a>
                    @endif
                    @if ($message->order)
                        <a href="{{ route('admin.orders.show', $message->order) }}" class="admin-message-link-chip">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M4 7h16v13H4V7Zm4-3.5v3.5m8-3.5v3.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Order {{ $message->order->number }}
                        </a>
                    @endif
                </div>
            @endif
        </header>

        <section class="order-panel admin-message-body">
            <p>{!! nl2br(e($message->message)) !!}</p>
        </section>

        <div class="admin-message-actions">
            <a href="mailto:{{ $message->email }}" class="btn btn-primary">Reply by email</a>
            <a href="{{ route('admin.messages.index') }}" class="btn btn-secondary">Back to messages</a>
        </div>
    </div>
@endsection
