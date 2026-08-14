@extends('layouts.app')

@section('title', __('store.address_edit').' — '.config('app.name'))

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.addresses.index') }}">{{ __('store.account_addresses') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.address_edit') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.address_edit') }}</h2>
        </header>

        @include('account.nav')

        <div class="account-card auth-card">
            <form method="POST" action="{{ localized_route('account.addresses.update', ['address' => $address]) }}" class="auth-form">
                @csrf
                @method('PUT')
                @include('partials.address-form-fields', ['address' => $address])
                <button type="submit" class="btn btn-primary">{{ __('store.address_save') }}</button>
            </form>
        </div>
    </div>
@endsection
