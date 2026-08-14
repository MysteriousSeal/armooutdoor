@extends('layouts.app')

@section('title', __('store.register_title').' — '.config('app.name'))
@section('meta_description', __('store.register_intro'))
@section('canonical', localized_route('register'))

@section('content')
    <div class="container">
        <div class="auth-page">
            <div class="auth-card">
                <h2>{{ __('store.register_title') }}</h2>
                <p class="auth-card-intro">{{ __('store.register_intro') }}</p>

                <form method="POST" action="{{ localized_route('register.store') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="name">{{ __('store.name') }}</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            value="{{ old('name') }}"
                            required
                            autofocus
                            autocomplete="name"
                            maxlength="80"
                        >
                        @error('name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
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
                        >
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

                    <button type="submit" class="btn btn-primary btn-block">{{ __('store.register_cta') }}</button>
                </form>

                <p class="auth-card-footer">
                    {{ __('store.has_account') }}
                    <a href="{{ localized_route('login') }}">{{ __('store.login') }}</a>
                </p>
            </div>
        </div>
    </div>
@endsection
