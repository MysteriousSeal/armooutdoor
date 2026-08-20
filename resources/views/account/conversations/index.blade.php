@extends('layouts.app')

@section('title', __('store.conversations_title').' — '.config('app.name'))
@section('canonical', localized_route('account.conversations.index'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/thread.css') }}">
@endpush

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.conversations_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.conversations_title') }}</h2>
            <p class="page-lede">{{ __('store.conversations_intro') }}</p>
        </header>

        @include('account.nav')

        @if ($conversations->isEmpty())
            <div class="empty-state">
                <p>{{ __('store.conversations_empty') }}</p>
                <a href="{{ localized_route('contact.show') }}" class="btn btn-primary">{{ __('store.conversations_new') }}</a>
            </div>
        @else
            <ul class="conversation-list">
                @foreach ($conversations as $conversation)
                    <li>
                        <a href="{{ localized_route('account.conversations.show', ['conversation' => $conversation]) }}" class="conversation-card {{ $conversation->hasUnreadForCustomer() ? 'is-unread' : '' }}">
                            <span class="conversation-card-main">
                                <span class="conversation-card-head">
                                    <span class="conversation-card-subject">{{ $conversation->subject }}</span>
                                    @if ($conversation->hasUnreadForCustomer())
                                        <span class="conversation-chip conversation-chip--unread">{{ __('store.conversations_unread') }}</span>
                                    @endif
                                    @if ($conversation->isClosed())
                                        <span class="conversation-chip">{{ __('store.conversations_status_closed') }}</span>
                                    @endif
                                </span>
                                <span class="conversation-card-snippet">
                                    {{ \Illuminate\Support\Str::limit($conversation->latestMessage?->body, 90) }}
                                </span>
                                <span class="conversation-card-meta">
                                    {{ __('store.conversation_started', ['date' => $conversation->created_at->format('d/m/Y')]) }}
                                    · {{ trans_choice('store.conversation_message_count', $conversation->messages_count, ['count' => $conversation->messages_count]) }}
                                </span>
                            </span>
                            <svg class="conversation-card-chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                <path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="conversation-list-actions">
                <a href="{{ localized_route('contact.show') }}" class="btn btn-primary">{{ __('store.conversations_new') }}</a>
            </div>
        @endif
    </div>
@endsection
