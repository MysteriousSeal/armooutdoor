@extends('layouts.app')

@php
    $entries = \App\Support\Glossary::resolved();
    $letters = range('A', 'Z');
    $present = $entries->pluck('initial')->unique()->all();
    $groups = $entries->groupBy('initial');
@endphp

@section('title', 'Glossaire du tir et de l\'airsoft — Armo Outdoor')
@section('meta_description', 'AEG, hop-up, joule, MED, diabolo, grille graduée, témoin de chambre vide : les mots du rayon définis un par un, chacun renvoyant au produit ou au guide où on le rencontre.')
@section('og_type', 'article')
@section('canonical', route('guides.glossaire'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'DefinedTermSet',
            'name' => 'Glossaire du tir et de l\'airsoft',
            'description' => 'Les mots du rayon, définis un par un et rattachés au produit ou au guide où on les rencontre.',
            'url' => route('guides.glossaire'),
            'inLanguage' => 'fr-FR',
            'publisher' => \App\Support\OrganizationSchema::reference(),
            'hasDefinedTerm' => $entries->map(fn (array $entry): array => array_filter([
                '@@type' => 'DefinedTerm',
                'name' => $entry['term'],
                'description' => $entry['definition'],
                'url' => route('guides.glossaire').'#'.\Illuminate\Support\Str::slug($entry['term']),
            ]))->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => 'Guides', 'item' => route('guides.index')],
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Glossaire', 'item' => route('guides.glossaire')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab">
        @include('guides.partials.hero', [
            'crumb' => 'Glossaire',
            'kicker' => 'Vocabulaire',
            'title' => 'Le glossaire',
            'lede' => 'Les mots que portent les fiches produit et les filtres du catalogue, définis un par un. Chacun dit où on le rencontre dans la boutique, et y mène.',
            'tags' => [$entries->count().' entrées'],
        ])

        {{-- The index tabs. Letters that open nothing are shown dead rather
             than hidden: an index that quietly skips G reads as broken. --}}
        <nav class="gloss-rail" aria-label="Index alphabétique">
            @foreach ($letters as $letter)
                @if (in_array($letter, $present, true))
                    <a href="#lettre-{{ $letter }}">{{ $letter }}</a>
                @else
                    <span aria-hidden="true">{{ $letter }}</span>
                @endif
            @endforeach
        </nav>

        {{-- Filtering needs JavaScript, so the field only appears once the
             page has it; the full list below is the answer without it. --}}
        <div class="gloss-search" data-gloss-search hidden>
            <label for="gloss-filter">Chercher un mot</label>
            <input type="search" id="gloss-filter" placeholder="hop-up, joule, diabolo…" autocomplete="off" data-gloss-input>
            <p class="gloss-search-count" role="status" data-gloss-count></p>
        </div>

        <div class="gloss-list" data-gloss-list>
            @foreach ($groups as $initial => $group)
                <section class="gloss-group" id="lettre-{{ $initial }}" data-gloss-group aria-labelledby="lettre-{{ $initial }}-title">
                    <h2 class="gloss-letter" id="lettre-{{ $initial }}-title">{{ $initial }}</h2>

                    <div class="gloss-entries">
                        @foreach ($group as $entry)
                            {{-- An article with its own heading rather than a
                                 definition list: forty headings are forty stops
                                 a screen reader can jump between, and the word
                                 and the place it lives need to sit in separate
                                 columns. --}}
                            <article
                                class="gloss-entry"
                                data-gloss-entry
                                data-gloss-terms="{{ \Illuminate\Support\Str::lower($entry['term'].' '.implode(' ', $entry['aliases'] ?? [])) }}"
                            >
                                <h3 class="gloss-term" id="{{ \Illuminate\Support\Str::slug($entry['term']) }}">{{ $entry['term'] }}</h3>
                                <p class="gloss-definition">{{ $entry['definition'] }}</p>

                                <div class="gloss-where is-{{ $entry['kind'] }}">
                                    <span class="gloss-source">{{ $entry['source'] }}</span>
                                    @if ($entry['url'] !== null)
                                        <a href="{{ $entry['url'] }}" class="gloss-goto">{{ $entry['label'] }}</a>
                                    @endif
                                    @if ($entry['count'] !== null)
                                        <p class="gloss-count">
                                            <strong>{{ $entry['count'] }}</strong>
                                            {{ $entry['count'] > 1 ? 'produits' : 'produit' }}
                                        </p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <p class="gloss-empty" data-gloss-empty hidden>Aucun mot ne correspond. Essayez une autre orthographe, ou parcourez l'index.</p>
        </div>

        <section class="glab-section" aria-labelledby="gloss-more-title">
            <h2 class="glab-title" id="gloss-more-title">Un mot <span class="glab-title-accent">manque ?</span></h2>
            <p class="glab-lede">
                Le glossaire suit ce que la boutique vend : il grandit quand le rayon grandit.
                Si un terme d'une fiche produit n'est pas ici, dites-le nous et il y sera.
            </p>

            <p class="glab-ctas">
                <a href="{{ route('guides.index') }}" class="btn btn-primary">Tous les guides</a>
                <a href="{{ route('contact.show') }}" class="btn btn-secondary">Nous signaler un mot</a>
            </p>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
