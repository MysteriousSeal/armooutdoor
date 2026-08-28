@extends('layouts.app')

@section('title', __('store.account_title').' — '.config('app.name'))
@section('canonical', localized_route('account.index'))

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.account_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.account_hello', ['name' => $user->name]) }}</h2>
            <p class="page-lede">{{ __('store.account_intro') }}</p>
        </header>

        @include('account.nav')

        <div class="account-hub">
            <a href="{{ localized_route('account.profile.edit') }}" class="account-hub-card">
                <span class="account-hub-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22">
                        <circle cx="12" cy="8" r="3.25" fill="none" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M5.5 19.25c.85-3.2 3.3-5 6.5-5s5.65 1.8 6.5 5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="account-hub-body">
                    <span class="home-kicker">01</span>
                    <h3>{{ __('store.account_details') }}</h3>
                    <p>{{ __('store.account_details_lede') }}</p>
                    <span class="account-hub-meta">{{ $user->email }}</span>
                </span>
            </a>

            <a href="{{ localized_route('account.addresses.index') }}" class="account-hub-card">
                <span class="account-hub-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22">
                        <path d="M12 21s6.5-5.2 6.5-10.2A6.5 6.5 0 0 0 12 4.3a6.5 6.5 0 0 0-6.5 6.5C5.5 15.8 12 21 12 21z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <circle cx="12" cy="10.8" r="2" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                </span>
                <span class="account-hub-body">
                    <span class="home-kicker">02</span>
                    <h3>{{ __('store.account_addresses') }}</h3>
                    <p>{{ __('store.account_addresses_lede') }}</p>
                    <span class="account-hub-meta">
                        {{ trans_choice('store.address_count', $addressCount, ['count' => $addressCount]) }}
                    </span>
                </span>
            </a>

            <a href="{{ localized_route('account.wishlist.index') }}" class="account-hub-card">
                <span class="account-hub-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22">
                        <path d="M12 20s-7.5-4.6-7.5-10A4.4 4.4 0 0 1 12 6.8 4.4 4.4 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="account-hub-body">
                    <span class="home-kicker">03</span>
                    <h3>{{ __('store.account_wishlist') }}</h3>
                    <p>{{ __('store.account_wishlist_lede') }}</p>
                    <span class="account-hub-meta">
                        {{ trans_choice('store.wishlist_count', $wishlistCount, ['count' => $wishlistCount]) }}
                    </span>
                </span>
            </a>

            <a href="{{ localized_route('orders.index') }}" class="account-hub-card">
                <span class="account-hub-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22">
                        <path d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M4 8.5 12 13l8-4.5M12 13v7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="account-hub-body">
                    <span class="home-kicker">04</span>
                    <h3>{{ __('store.account_orders') }}</h3>
                    <p>{{ __('store.account_orders_lede') }}</p>
                    <span class="account-hub-meta">
                        {{ trans_choice('store.order_count', $orderCount, ['count' => $orderCount]) }}
                    </span>
                </span>
            </a>

            <a href="{{ localized_route('account.discounts.index') }}" class="account-hub-card">
                <span class="account-hub-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22">
                        <path d="M4 9V6.5A1.5 1.5 0 0 1 5.5 5h13A1.5 1.5 0 0 1 20 6.5V9a2.4 2.4 0 0 0 0 6v2.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5V15a2.4 2.4 0 0 0 0-6z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M14 9.5 10 14.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="account-hub-body">
                    <span class="home-kicker">05</span>
                    <h3>{{ __('store.account_discounts') }}</h3>
                    <p>{{ __('store.account_discounts_lede') }}</p>
                    <span class="account-hub-meta">
                        {{ trans_choice('store.discount_count', $usableDiscountCount, ['count' => $usableDiscountCount]) }}
                    </span>
                </span>
            </a>

            <a href="{{ localized_route('account.conversations.index') }}" class="account-hub-card">
                <span class="account-hub-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22">
                        <path d="M20 12.5c0 3.4-3.6 6.2-8 6.2-.9 0-1.8-.1-2.6-.3L4 20l1.4-3.4A5.9 5.9 0 0 1 4 12.5c0-3.4 3.6-6.2 8-6.2s8 2.8 8 6.2z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="account-hub-body">
                    <span class="home-kicker">06</span>
                    <h3>{{ __('store.account_conversations') }}</h3>
                    <p>{{ __('store.account_conversations_lede') }}</p>
                    <span class="account-hub-meta">
                        @if ($unreadConversationCount > 0)
                            <span class="conversation-chip conversation-chip--unread">
                                {{ trans_choice('store.conversation_unread_count', $unreadConversationCount, ['count' => $unreadConversationCount]) }}
                            </span>
                        @else
                            {{ trans_choice('store.conversation_count', $conversationCount, ['count' => $conversationCount]) }}
                        @endif
                    </span>
                </span>
            </a>
        </div>

        <div class="account-hub-logout">
            <form action="{{ localized_route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="account-logout-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                        <path d="M15 4h-4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M19 12H9m10 0-3-3m3 3-3 3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ __('store.logout') }}
                </button>
            </form>
        </div>
    </div>
@endsection
