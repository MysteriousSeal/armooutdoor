@php
    // A 403 can be raised from either side of the app. Rendering the admin
    // layout to a customer would show them the whole back-office nav and its
    // shop-wide badge counts, so pick the chrome from who is actually asking.
    $isAdmin = auth()->check() && auth()->user()->isAdmin();
@endphp

@extends($isAdmin ? 'layouts.admin' : 'layouts.app')

@section('title', $isAdmin ? 'Access denied' : __('store.forbidden_title').' — '.config('app.name'))

@unless ($isAdmin)
    @push('head')
        <link rel="stylesheet" href="{{ versioned_asset('css/errors.css') }}">
    @endpush
@endunless

@section('content')
    @if ($isAdmin)
        <div class="admin-list-page">
            <div class="admin-403">
                <span class="admin-403-icon">
                    <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
                        <rect x="5" y="11" width="14" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.75"/>
                        <path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                    </svg>
                </span>
                <h2 class="admin-403-title">You don't have access to this</h2>
                <p class="admin-403-lede">This area is limited to owners. Ask an owner if you think you should have access.</p>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Back to dashboard</a>
            </div>
        </div>
    @else
        <div class="container">
            <section class="error-page">
                <p class="error-page-code" aria-hidden="true">403</p>
                <h1 class="error-page-title">{{ __('store.forbidden_title') }}</h1>
                <p class="error-page-lede">{{ __('store.forbidden_lede') }}</p>

                <div class="error-page-actions">
                    <a href="{{ localized_route('home') }}" class="btn btn-primary">{{ __('store.forbidden_home') }}</a>
                    @auth
                        <a href="{{ localized_route('account.index') }}" class="btn btn-secondary">{{ __('store.account_title') }}</a>
                    @endauth
                </div>
            </section>
        </div>
    @endif
@endsection
