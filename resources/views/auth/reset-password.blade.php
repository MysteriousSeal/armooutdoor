@extends('layouts.app')

@section('title', __('store.reset_password_title').' — '.config('app.name'))

@section('content')
    <div class="container">
        <div class="auth-page">
            <div class="auth-card">
                <h2>{{ __('store.reset_password_title') }}</h2>
                <p class="auth-card-intro">{{ __('store.reset_password_intro') }}</p>

                <form method="POST" action="{{ localized_route('password.update') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="form-group">
                        <label for="email">{{ __('store.email') }}</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="{{ old('email', $email) }}"
                            required
                            autofocus
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

                    <button type="submit" class="btn btn-primary btn-block">{{ __('store.reset_password_cta') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
