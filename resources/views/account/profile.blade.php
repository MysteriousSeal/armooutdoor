@extends('layouts.app')

@section('title', __('store.account_details').' — '.config('app.name'))
@section('canonical', localized_route('account.profile.edit'))

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.account_details') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.account_details') }}</h2>
            <p class="page-lede">{{ __('store.account_details_lede') }}</p>
        </header>

        @include('account.nav')

        <div class="auth-card account-card">
            <form method="POST" action="{{ localized_route('account.profile.update') }}" class="auth-form">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">{{ __('store.first_name') }}</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required maxlength="80" autocomplete="given-name">
                        @error('first_name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label for="last_name">{{ __('store.last_name') }}</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}" required maxlength="80" autocomplete="family-name">
                        @error('last_name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">{{ __('store.email') }}</label>
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required autocomplete="email">
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <h3 class="account-form-heading">{{ __('store.password_section') }}</h3>
                <p class="form-hint">{{ __('store.password_section_hint') }}</p>

                <div class="form-group">
                    <label for="current_password">{{ __('store.current_password') }}</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password">
                    @error('current_password') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="password">{{ __('store.new_password') }}</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password">
                    @error('password') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation">{{ __('store.password_confirmation') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary">{{ __('store.save_profile') }}</button>
            </form>
        </div>
    </div>
@endsection
