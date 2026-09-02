@extends('layouts.app')

@section('title', __('store.register_title').' — '.config('app.name'))
@section('meta_description', __('store.register_intro'))
@section('canonical', localized_route('register'))

@section('content')
    <div class="container">
        <div class="auth-page">
            <div class="auth-layout">
                @include('auth.partials.brand-panel')
                <div class="auth-card">
                <h1>{{ __('store.register_title') }}</h1>
                <p class="auth-card-intro">{{ __('store.register_intro') }}</p>

                <form method="POST" action="{{ localized_route('register.store') }}" class="auth-form" novalidate data-register-form>
                    @csrf

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">{{ __('store.first_name') }}</label>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                class="form-control"
                                value="{{ old('first_name') }}"
                                required
                                autofocus
                                autocomplete="given-name"
                                maxlength="80"
                            >
                            @error('first_name')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="last_name">{{ __('store.last_name') }}</label>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                class="form-control"
                                value="{{ old('last_name') }}"
                                required
                                autocomplete="family-name"
                                maxlength="80"
                            >
                            @error('last_name')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">{{ __('store.email') }}</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="{{ old('email') }}"
                            required
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
                            autocomplete="new-password"
                            minlength="8"
                        >
                        <p class="form-hint">{{ __('store.password_min_hint') }}</p>
                        @error('password')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">{{ __('store.password_confirmation') }}</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-check auth-legal-note">
                            <input type="checkbox" name="terms" value="1" data-terms @checked(old('terms'))>
                            <span>
                                En créant un compte, vous acceptez les
                                <a href="{{ route('legal.terms') }}">conditions générales de vente</a> et la
                                <a href="{{ route('legal.privacy') }}">politique de confidentialité</a>.
                            </span>
                        </label>
                        {{-- Filled by the script on a submit without the box checked;
                             the server refuses just the same without JavaScript. --}}
                        <p class="form-error" data-terms-warning hidden>Vous devez accepter les conditions pour créer un compte.</p>
                        @error('terms')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">{{ __('store.register_cta') }}</button>
                </form>

                <p class="auth-card-footer">
                    {{ __('store.has_account') }}
                    <a href="{{ localized_route('login') }}">{{ __('store.login') }}</a>
                </p>
            </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/password-toggle.js') }}" defer></script>
    <script src="{{ versioned_asset('js/register-validate.js') }}" defer></script>
@endpush
