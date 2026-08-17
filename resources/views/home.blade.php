@extends('layouts.app')

@section('title', config('app.name'))
@section('meta_description', __('store.meta_home'))
@section('canonical', localized_route('home'))

@section('content')
    @php
        $shopUrl = $firstCategory
            ? localized_route('categories.show', ['category' => $firstCategory->slug])
            : localized_route('search');
    @endphp

    <div class="container home">
        <section class="home-hero" aria-labelledby="home-hero-title" style="--hero-image: url('{{ asset('images/hero.jpg') }}')">
            <div class="home-hero-copy">
                <h2 class="home-hero-title" id="home-hero-title">
                    Équipez-vous<br>pour le stand<br>et le terrain
                </h2>
                <p class="home-hero-text">
                    Équipement sélectionné pour<br>
                    le tir sportif, la chasse, l’airgun<br>
                    et l’aventure en plein air.
                </p>
                <div class="home-hero-actions">
                    <a href="{{ $shopUrl }}" class="btn btn-primary">{{ __('store.hero_cta') }}</a>
                    <a href="{{ $shopUrl }}" class="btn home-hero-ghost">{{ __('store.home_browse') }}</a>
                </div>
            </div>
            <div class="home-hero-dots" aria-hidden="true">
                <span class="is-active"></span>
                <span></span>
                <span></span>
            </div>
        </section>

        <ul class="home-trust">
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7h11v10H3z"/>
                        <path d="M14 10h4l3 3v4h-7"/>
                        <circle cx="7" cy="18" r="1.5"/>
                        <circle cx="18" cy="18" r="1.5"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_ship_title') }}</strong>
                    <span>
                        @if ($freeShippingAmount)
                            {{ __('store.home_hero_ship_from', ['amount' => $freeShippingAmount]) }}
                        @else
                            {{ __('store.home_hero_ship_plain') }}
                        @endif
                    </span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3 5 6v6c0 5 3.2 8.2 7 9 3.8-.8 7-4 7-9V6z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_pay_title') }}</strong>
                    <span>{{ __('store.home_hero_pay_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5z"/>
                        <path d="M12 12v8"/>
                        <path d="M12 12 4.4 8.2"/>
                        <path d="m12 12 7.6-3.8"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_track_title') }}</strong>
                    <span>{{ __('store.home_hero_track_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="6" width="17" height="13" rx="1.5"/>
                        <path d="m4.5 8 7.5 6 7.5-6"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_help_title') }}</strong>
                    <span>{{ __('store.home_hero_help_text') }}</span>
                </span>
            </li>
        </ul>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    <script type="application/ld+json">
        {{-- The key is written @@context so Blade emits a literal "@context":
             left bare, Blade compiles it as its own @context directive and the
             key is replaced by PHP, leaving the JSON-LD without @context. --}}
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => localized_route('home'),
            'inLanguage' => 'fr-FR',
            'description' => __('store.meta_home'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush
