@extends('layouts.app')

@section('title', __('store.contact_title').' — '.config('app.name'))
@section('meta_description', __('store.contact_intro'))
@section('canonical', localized_route('contact.show'))

@push('head')
    {{-- The hero's styles live in categories.css, same as every other page
         that uses partials.page-hero. --}}
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/contact.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/contact-form.js') }}" defer></script>
@endpush

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.contact_title') }}</span>
        </nav>

        @include('partials.page-hero', [
            'kicker' => __('store.contact_hero_kicker'),
            'title' => __('store.contact_title'),
            'description' => __('store.contact_intro'),
            'tags' => [
                __('store.contact_hero_tag_reply'),
                __('store.contact_hero_tag_team'),
            ],
            'titleNoWrap' => true,
        ])

        <div class="contact-layout">
            <form id="contact-form" method="POST" action="{{ localized_route('contact.store') }}" class="contact-form" novalidate>
                @csrf

                <div class="contact-form-honeypot" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-row form-row--inline">
                    <div class="form-group">
                        <label for="name">{{ __('store.contact_name') }}</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            value="{{ old('name', $prefillName) }}"
                            maxlength="120"
                            @if ($identityLocked) disabled @else required data-validate @endif
                        >
                        @if ($identityLocked)
                            <p class="form-hint">{{ __('store.contact_name_locked') }}</p>
                        @endif
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">{{ __('store.contact_email') }}</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="{{ old('email', $prefillEmail) }}"
                            maxlength="255"
                            @if ($identityLocked) disabled @else required data-validate @endif
                        >
                        @if ($identityLocked)
                            <p class="form-hint">{{ __('store.contact_email_locked') }}</p>
                        @endif
                        @error('email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">{{ __('store.contact_subject') }}</label>
                    <input type="text" id="subject" name="subject" class="form-control" value="{{ old('subject') }}" maxlength="150" required data-validate>
                    @error('subject') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                @if ($orders->isNotEmpty())
                    <div class="form-group">
                        <label for="order_id">{{ __('store.contact_order') }}</label>
                        <select id="order_id" name="order_id" class="form-control">
                            <option value="">{{ __('store.contact_order_none') }}</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>
                                    {{ $order->number }} · {{ $order->created_at->format('d/m/Y') }} · {{ $order->formattedTotal() }}
                                </option>
                            @endforeach
                        </select>
                        @error('order_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="form-group">
                    <label for="message">{{ __('store.contact_message') }}</label>
                    <textarea id="message" name="message" class="form-control" rows="7" maxlength="5000" required data-validate>{{ old('message') }}</textarea>
                    @error('message') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('store.contact_submit') }}</button>
            </form>

            <aside class="contact-info">
                <div class="contact-info-card">
                    <h3>{{ __('store.contact_info_title') }}</h3>
                    <dl class="contact-info-list">
                        @if (trim($company->contact_email) !== '')
                            <div>
                                <dt>{{ __('store.contact_info_email') }}</dt>
                                <dd><a href="mailto:{{ $company->contact_email }}">{{ $company->contact_email }}</a></dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="contact-info-card">
                    <p class="contact-info-faq-title">{{ __('store.contact_info_faq_title') }}</p>
                    <p class="contact-info-faq-text">{{ __('store.contact_info_faq_text') }}</p>
                    <a href="{{ route('faq') }}" class="btn btn-secondary">{{ __('store.contact_info_faq_cta') }}</a>
                </div>
            </aside>
        </div>
    </div>
@endsection
