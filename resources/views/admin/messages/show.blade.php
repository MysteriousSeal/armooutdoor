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
                @if ($message->user)
                    · <a href="{{ route('admin.customers.show', $message->user) }}">Customer account</a>
                @endif
            </p>
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
