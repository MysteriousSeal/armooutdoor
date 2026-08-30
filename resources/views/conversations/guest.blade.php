@extends('layouts.app')

@section('title', $conversation->subject.' — '.config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/thread.css') }}">
    {{-- A private link is not for search engines. --}}
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="container account-page guest-conversation-page">
        <div class="thread-card">
            <header class="thread-card-head">
                <p class="guest-conversation-kicker">{{ __('store.guest_conversation_kicker') }}</p>
                <h2 class="thread-card-title">
                    {{ $conversation->subject }}
                    @if ($conversation->isClosed())
                        <span class="thread-card-chip">{{ __('store.conversations_status_closed') }}</span>
                    @endif
                </h2>
                <p class="thread-card-meta">
                    {{ __('store.conversation_started', ['date' => $conversation->created_at->format('d/m/Y')]) }}
                    · {{ $conversation->name }}
                </p>
            </header>

            <div class="thread-card-body">
                @include('partials.conversation-thread', ['conversation' => $conversation, 'viewer' => 'customer'])
            </div>

            @if ($conversation->isClosed())
                <div class="thread-card-foot thread-closed-note">
                    <p class="form-hint">{{ __('store.conversation_closed_note') }}</p>
                    <a href="{{ localized_route('contact.show') }}" class="btn btn-primary">{{ __('store.conversations_new') }}</a>
                </div>
            @else
                <form
                    id="conversation-reply-form"
                    method="POST"
                    action="{{ route('guest.conversations.reply', ['token' => $conversation->guest_token]) }}"
                    class="thread-card-foot thread-composer"
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
                        rows="4"
                        maxlength="5000"
                        required
                        placeholder="{{ __('store.conversation_reply_placeholder') }}"
                    ></textarea>
                    @error('body') <p class="form-error">{{ $message }}</p> @enderror
                    <div class="thread-composer-actions">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-loader" aria-hidden="true"></span>
                            {{ __('store.conversation_reply_send') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <p class="guest-conversation-note">
            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
                <rect x="5" y="10" width="14" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.75"/>
                <path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            {{ __('store.guest_conversation_note') }}
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/conversation-reply.js') }}" defer></script>
@endpush
