@extends('layouts.app')

@section('title', __('store.contact_title').' — '.config('app.name'))
@section('meta_description', __('store.contact_intro'))
@section('canonical', localized_route('contact.show'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/contact.css') }}">
@endpush

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.contact_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.contact_title') }}</h2>
            <p class="page-lede">{{ __('store.contact_intro') }}</p>
        </header>

        <div class="contact-layout">
            <form method="POST" action="{{ localized_route('contact.store') }}" class="contact-form">
                @csrf

                <div class="contact-form-honeypot" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-row form-row--inline">
                    <div class="form-group">
                        <label for="name">{{ __('store.contact_name') }}</label>
                        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $prefillName) }}" maxlength="120" required>
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">{{ __('store.contact_email') }}</label>
                        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $prefillEmail) }}" maxlength="255" required>
                        @error('email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">{{ __('store.contact_subject') }}</label>
                    <input type="text" id="subject" name="subject" class="form-control" value="{{ old('subject') }}" maxlength="150" required>
                    @error('subject') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="message">{{ __('store.contact_message') }}</label>
                    <textarea id="message" name="message" class="form-control" rows="7" maxlength="5000" required>{{ old('message') }}</textarea>
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
                        @if (trim($company->phone) !== '')
                            <div>
                                <dt>{{ __('store.contact_info_phone') }}</dt>
                                <dd><a href="tel:{{ $company->phone }}">{{ $company->formattedPhone() }}</a></dd>
                            </div>
                        @endif
                        @if ($company->addressLines() !== [])
                            <div>
                                <dt>{{ __('store.contact_info_address') }}</dt>
                                <dd>
                                    @foreach ($company->addressLines() as $line)
                                        {{ $line }}<br>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="contact-info-card contact-info-card--faq">
                    <p class="contact-info-faq-title">{{ __('store.contact_info_faq_title') }}</p>
                    <p class="contact-info-faq-text">{{ __('store.contact_info_faq_text') }}</p>
                    <a href="{{ route('faq') }}" class="btn btn-secondary">{{ __('store.contact_info_faq_cta') }}</a>
                </div>
            </aside>
        </div>
    </div>
@endsection
