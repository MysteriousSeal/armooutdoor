@extends('layouts.app')

@section('title', $conversation->subject.' — '.config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/thread.css') }}">
@endpush

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.conversations.index') }}">{{ __('store.conversations_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ $conversation->subject }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ $conversation->subject }}</h2>
            <p class="page-lede">
                {{ __('store.conversation_started', ['date' => $conversation->created_at->format('d/m/Y')]) }}
                @if ($conversation->isClosed())
                    · {{ __('store.conversations_status_closed') }}
                @endif
            </p>
            @if ($conversation->order)
                <p class="conversation-order-link">
                    <a href="{{ localized_route('orders.show', ['order' => $conversation->order->number]) }}">
                        {{ __('store.conversation_order', ['number' => $conversation->order->number]) }}
                    </a>
                </p>
            @endif
        </header>

        @include('account.nav')

        <ol class="thread thread--as-customer" id="conversation-thread">
            @foreach ($conversation->messages as $message)
                <li class="thread-item {{ $message->isFromAdmin() ? 'thread-item--admin' : 'thread-item--customer' }}">
                    <div class="thread-bubble">
                        <div class="thread-meta">
                            <span class="thread-author">{{ $message->authorLabel() }}</span>
                            <time class="thread-time" datetime="{{ $message->created_at->toIso8601String() }}">
                                {{ $message->created_at->format('d/m/Y · H:i') }}
                            </time>
                        </div>
                        <p class="thread-body">{!! nl2br(e($message->body)) !!}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($conversation->isClosed())
            <div class="thread-panel thread-closed-note">
                <p class="form-hint">{{ __('store.conversation_closed_note') }}</p>
                <a href="{{ localized_route('contact.show') }}" class="btn btn-primary">{{ __('store.conversations_new') }}</a>
            </div>
        @else
            <form
                id="conversation-reply-form"
                method="POST"
                action="{{ localized_route('account.conversations.reply', ['conversation' => $conversation]) }}"
                class="thread-panel thread-composer"
                data-thread-item-class="thread-item--customer"
                data-required-message="{{ __('store.conversation_reply_required') }}"
                novalidate
            >
                @csrf
                <label for="body" class="thread-panel-title">{{ __('store.conversation_reply_label') }}</label>
                <textarea
                    id="body"
                    name="body"
                    class="form-control"
                    rows="5"
                    maxlength="5000"
                    required
                    placeholder="{{ __('store.conversation_reply_placeholder') }}"
                ></textarea>
                @error('body') <p class="form-error">{{ $message }}</p> @enderror
                <div class="thread-composer-actions">
                    <button type="submit" class="btn btn-primary">{{ __('store.conversation_reply_send') }}</button>
                </div>
            </form>
        @endif

        <div class="conversation-list-actions">
            <a href="{{ localized_route('account.conversations.index') }}" class="btn btn-secondary">{{ __('store.conversation_back') }}</a>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/conversation-reply.js') }}" defer></script>
@endpush
