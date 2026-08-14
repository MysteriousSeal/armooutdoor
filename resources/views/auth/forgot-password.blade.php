@extends('layouts.app')

@section('title', __('store.forgot_password_title').' — '.config('app.name'))
@section('canonical', localized_route('password.request'))

@section('content')
    <div class="container">
        <div class="auth-page">
            <div class="auth-card">
                <h2>{{ __('store.forgot_password_title') }}</h2>
                <p class="auth-card-intro">{{ __('store.forgot_password_intro') }}</p>

                <form method="POST" action="{{ localized_route('password.email') }}" class="auth-form">
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
                        >
                        @error('email')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">{{ __('store.send_reset_link') }}</button>
                </form>

                <p class="auth-card-footer">
                    <a href="{{ localized_route('login') }}">{{ __('store.login') }}</a>
                </p>
            </div>
        </div>
    </div>
@endsection
