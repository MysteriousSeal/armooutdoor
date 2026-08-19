@extends('layouts.app')

@section('title', 'Page introuvable — '.config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/errors.css') }}">
@endpush

@section('content')
    @php
        $popularCategories = \App\Models\Category::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();
    @endphp
    <div class="container">
        <section class="error-page">
            <p class="error-page-code" aria-hidden="true">404</p>
            <h1 class="error-page-title">Cette page s’est égarée</h1>
            <p class="error-page-lede">
                La page que vous cherchez n’existe pas, ou plus. Essayez une recherche, ou repartez de l’accueil.
            </p>

            <form action="{{ localized_route('search') }}" method="GET" class="error-404-search" role="search">
                <label for="error-404-search-input" class="sr-only">Rechercher dans la boutique</label>
                <svg class="error-404-search-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.75"/>
                    <path d="M20 20l-4.3-4.3" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                </svg>
                <input
                    type="search"
                    id="error-404-search-input"
                    name="q"
                    class="error-404-search-input"
                    placeholder="Rechercher un produit…"
                    autofocus
                >
                <button type="submit" class="btn btn-primary">Rechercher</button>
            </form>

            <a href="{{ localized_route('home') }}" class="error-page-home-link">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                    <path d="M19 12H5M11 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Retour à l’accueil
            </a>
        </section>

        @if ($popularCategories->isNotEmpty())
            <section class="error-404-categories">
                <p class="error-404-categories-title">Ou explorez une catégorie</p>
                <div class="error-404-categories-grid">
                    @foreach ($popularCategories->take(6) as $category)
                        <a href="{{ localized_route('categories.show', ['category' => $category->slug]) }}" class="error-404-category-chip">
                            {{ $category->localizedName() }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
