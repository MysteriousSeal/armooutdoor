@extends('layouts.app')

@section('title', 'Erreur serveur — '.config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/errors.css') }}">
@endpush

@section('content')
    <div class="container">
        <section class="error-page">
            <p class="error-page-code" aria-hidden="true">500</p>
            <h1 class="error-page-title">Une erreur est survenue</h1>
            <p class="error-page-lede">
                Quelque chose s’est mal passé de notre côté. Réessayez dans quelques instants, ou revenez à l’accueil.
            </p>

            <div class="error-page-actions">
                <a href="{{ url()->current() }}" class="btn btn-primary">Réessayer</a>
                <a href="{{ localized_route('home') }}" class="error-page-home-link">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                        <path d="M19 12H5M11 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Retour à l’accueil
                </a>
            </div>
        </section>
    </div>
@endsection
