@extends('layouts.app')

@section('title', __('store.login_title').' — '.config('app.name'))
@section('meta_description', __('store.login_intro'))
@section('canonical', localized_route('login'))

@section('content')
    <div class="container">
        <div class="auth-page">
            <div class="auth-layout">
                @include('auth.partials.brand-panel')
                <div class="auth-card">
                <h1>{{ __('store.login_title') }}</h1>
                <p class="auth-card-intro">{{ __('store.login_intro') }}</p>

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
                            autofocus
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

                    <button type="submit" class="btn btn-primary btn-block">{{ __('store.login_cta') }}</button>
                </form>

                <p class="auth-card-footer">
                    {{ __('store.no_account') }}
                    <a href="{{ localized_route('register') }}">{{ __('store.register') }}</a>
                </p>
            </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/password-toggle.js') }}" defer></script>
@endpush
