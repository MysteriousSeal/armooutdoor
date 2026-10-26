@extends('layouts.app')

@section('title', __('store.login_title').' — '.config('app.name'))
@section('meta_description', __('store.login_intro'))
@section('canonical', localized_route('login'))

@section('content')
    <div class="container">
        <div class="auth-page">
            <div class="auth-layout {{ $checkoutLines ? 'auth-layout--checkout' : '' }}">
                @unless ($checkoutLines)
                    @include('auth.partials.brand-panel')
                @endunless
                <div class="auth-card">
                @if ($checkoutLines)
                    <h1>{{ __('store.login_checkout_title') }}</h1>
                    <p class="auth-card-intro">{{ __('store.login_checkout_intro') }}</p>

                    @include('auth.partials.checkout-cart')

                    @include('auth.partials.google', ['divider' => false])

                    <a href="{{ localized_route('register') }}" class="btn btn-primary btn-block auth-checkout-register">{{ __('store.register') }}</a>

                    <h2 class="auth-divider auth-divider--heading">{{ __('store.login_checkout_existing') }}</h2>
                @else
                    <h1>{{ __('store.login_title') }}</h1>
                    <p class="auth-card-intro">{{ __('store.login_intro') }}</p>

                    @include('auth.partials.google')
                @endif

                <form method="POST" action="{{ localized_route('login.store') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="email">{{ __('store.email') }}</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="{{ old('email') }}"
                            required
                            @unless ($checkoutLines) autofocus @endunless
                            autocomplete="email"
                            autocapitalize="none"
                            spellcheck="false"
                        >
                        @error('email')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">{{ __('store.password') }}</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            required
                            autocomplete="current-password"
                        >
                        @error('password')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group form-group--inline">
                        <label class="form-check">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember', true))>
                            {{ __('store.remember') }}
                        </label>
                        <a href="{{ localized_route('password.request') }}" class="auth-form-link">{{ __('store.forgot_password') }}</a>
                    </div>

                    <button type="submit" class="btn {{ $checkoutLines ? 'btn-secondary auth-login-submit--outline' : 'btn-primary' }} btn-block">{{ __('store.login_cta') }}</button>
                </form>

                @unless ($checkoutLines)
                    <p class="auth-card-footer">
                        {{ __('store.no_account') }}
                        <a href="{{ localized_route('register') }}">{{ __('store.register') }}</a>
                    </p>
                @endunless
            </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/password-toggle.js') }}" defer></script>
@endpush
