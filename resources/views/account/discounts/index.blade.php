@extends('layouts.app')

@section('title', __('store.discounts_title').' — '.config('app.name'))
@section('canonical', localized_route('account.discounts.index'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/account-discounts.css') }}">
@endpush

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.discounts_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.discounts_title') }}</h2>
            <p class="page-lede">{{ __('store.discounts_intro') }}</p>
        </header>

        @include('account.nav')

        @if ($codes->isEmpty())
            <div class="empty-state">
                <p>{{ __('store.discounts_empty') }}</p>
                <a href="{{ localized_route('home') }}" class="btn btn-primary">{{ __('store.discounts_empty_cta') }}</a>
            </div>
        @else
            <ul class="voucher-list">
                @foreach ($codes as $code)
                    @php($usesLeft = $code->remainingUsesFor(auth()->user()))
                    <li class="voucher">
                        <div class="voucher-value">
                            <span class="voucher-amount">{{ $code->customerLabel() }}</span>
                        </div>

                        <div class="voucher-body">
                            <p class="voucher-code">
                                <span class="voucher-code-text">{{ $code->code }}</span>
                            </p>
                            <p class="voucher-meta">
                                {{ $code->customerDeadlineLabel() ?? __('store.discount_code_no_deadline') }}
                                ·
                                {{ $usesLeft === null
                                    ? __('store.discount_code_uses_unlimited')
                                    : trans_choice('store.discount_code_uses_left', $usesLeft, ['count' => $usesLeft]) }}
                            </p>

                            @if ($code->ends_at)
                                <p class="voucher-countdown-row">
                                    <span
                                        class="voucher-countdown @if ($code->isEndingSoon()) is-urgent @endif"
                                        data-countdown-to="{{ $code->ends_at->toIso8601String() }}"
                                        data-countdown-urgent-hours="48"
                                        data-countdown-expired="{{ __('store.discount_code_expired') }}"
                                        data-countdown-template="{{ __('store.discount_code_expires_in', ['time' => ':time']) }}"
                                    >
                                        <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.75"/>
                                            <path d="M12 7v5.25l3.25 1.9" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span class="voucher-countdown-text">{{ $code->countdownLabel() }}</span>
                                    </span>
                                </p>
                            @endif
                        </div>

                        <button
                            type="button"
                            class="btn btn-sm btn-secondary voucher-cta"
                            data-copy-code="{{ $code->code }}"
                            data-copied-message="{{ __('store.discount_code_copied') }}"
                        >
                            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
                                <rect x="9" y="9" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/>
                                <path d="M6 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            {{ __('store.discount_code_copy') }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/account-discount-copy.js') }}" defer></script>
    <script src="{{ asset('js/account-discount-countdown.js') }}" defer></script>
@endpush
