@extends('layouts.app')

@section('title', __('store.account_addresses').' — '.config('app.name'))
@section('canonical', localized_route('account.addresses.index'))

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.account_addresses') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.account_addresses') }}</h2>
            <p class="page-lede">{{ __('store.account_addresses_lede') }}</p>
        </header>

        @include('account.nav')

        @if ($addresses->isEmpty())
            <p class="checkout-hint">{{ __('store.address_empty') }}</p>
        @else
            <div class="account-address-list">
                @foreach ($addresses as $address)
                    <article class="account-address-card">
                        <div>
                            <p class="choice-card-title">
                                {{ $address->recipientName() }}
                                @if ($address->label)
                                    <span class="choice-card-label">{{ $address->label }}</span>
                                @endif
                                @if ($address->is_default)
                                    <span class="choice-card-badge">{{ __('store.default_badge') }}</span>
                                @endif
                            </p>
                            <p class="choice-card-meta">
                                {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif
                            </p>
                            <p class="choice-card-meta">
                                {{ $address->postal_code }} {{ $address->city }} · {{ $address->countryName() }}
                            </p>
                            @if ($address->phone)
                                <p class="choice-card-meta">{{ $address->phone }}</p>
                            @endif
                        </div>

                        <div class="account-address-actions">
                            <a href="{{ localized_route('account.addresses.edit', ['address' => $address]) }}" class="btn btn-sm btn-secondary">
                                {{ __('store.edit') }}
                            </a>

                            @unless ($address->is_default)
                                <form method="POST" action="{{ localized_route('account.addresses.default', ['address' => $address]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.make_default') }}</button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ localized_route('account.addresses.destroy', ['address' => $address]) }}" onsubmit="return confirm(@json(__('store.address_delete_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.remove') }}</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <section class="account-card auth-card">
            <h3 class="account-form-heading">{{ __('store.address_new') }}</h3>
            <form method="POST" action="{{ localized_route('account.addresses.store') }}" class="auth-form">
                @csrf
                @include('partials.address-form-fields')
                <button type="submit" class="btn btn-primary">{{ __('store.address_save') }}</button>
            </form>
        </section>
    </div>
@endsection
