@extends('layouts.app')

@section('title', __('store.legal_notice_title').' — '.config('app.name'))
@section('canonical', route('legal.notice'))

@push('head')
    {{-- The section itself has no page, so it is not a step a trail can
         name: Google wants an address for every element but the last. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => __('store.legal_notice_title'), 'item' => route('legal.notice')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container legal-wrap">
        @include('legal.partials.chrome', [
            'title' => __('store.legal_notice_title'),
            'lede' => "Qui édite ce site, qui l'héberge, et comment nous joindre.",
            'page' => 'notice',
        ])

        <div class="legal-layout">
            @include('legal.partials.nav')

            <article class="legal-doc">
            @unless ($company->isComplete())
                <p class="legal-notice">
                    Certaines informations de cette page sont encore des espaces réservés. Complétez-les dans
                    <a href="{{ route('admin.settings.company.edit') }}">l'administration → Réglages → Company &amp; legal</a>.
                </p>
            @endunless

            <h2>Éditeur du site</h2>
            <dl class="legal-facts">
                <div>
                    <dt>Société</dt>
                    <dd>
                        {{ $company->value('company_name') }}
                        @if ($company->value('legal_form') !== '')
                            · {{ $company->value('legal_form') }}
                        @endif
                    </dd>
                </div>
                @if ($company->value('share_capital') !== '')
                    <div>
                        <dt>Capital</dt>
                        <dd>{{ $company->value('share_capital') }}</dd>
                    </div>
                @endif
                <div>
                    <dt>SIRET</dt>
                    <dd>{{ $company->value('siret') }}</dd>
                </div>
                <div>
                    <dt>Siège social</dt>
                    <dd>{{ $company->value('address') }}</dd>
                </div>
                <div>
                    <dt>TVA</dt>
                    <dd>{{ $company->vatMention() }}</dd>
                </div>
                <div>
                    <dt>E-mail</dt>
                    <dd>{{ $company->value('contact_email') }}</dd>
                </div>
                <div>
                    <dt>Téléphone</dt>
                    <dd>{{ $company->value('phone') }}</dd>
                </div>
            </dl>

            <h2>Directeur de la publication</h2>
            <p>{{ $company->value('publication_director') }}.</p>

            <h2>Hébergement</h2>
            <p>
                Le site est hébergé par {{ $company->value('host_name') }}, {{ $company->value('host_address') }},
                {{ $company->value('host_phone') }}.
            </p>

            <h2>Propriété intellectuelle</h2>
            <p>
                L'ensemble des éléments du site (textes, images, logos, mise en page, éléments graphiques) est protégé
                par le droit d'auteur et le droit des marques. Toute reproduction, représentation ou exploitation, totale
                ou partielle, sans autorisation préalable est interdite.
            </p>

            <h2>Données personnelles</h2>
            <p>
                Le traitement des données personnelles collectées sur le site est décrit dans notre
                <a href="{{ route('legal.privacy') }}">{{ __('store.legal_privacy_title') }}</a>.
            </p>

            <h2>Cookies</h2>
            <p>
                Le site utilise des cookies strictement nécessaires à son fonctionnement (panier, session, préférence
                d'affichage). Aucun cookie de mesure d'audience ou publicitaire n'est déposé sans consentement préalable.
            </p>

            <h2>Droit applicable et litiges</h2>
            <p>
                Le présent site et les présentes mentions légales sont soumis au droit français. En cas de litige, les
                tribunaux français seront seuls compétents.
            </p>
        </article>
        </div>
    </div>
@endsection
