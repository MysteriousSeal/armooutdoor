@extends('layouts.app')

@section('title', __('store.legal_withdrawal_title').' — '.config('app.name'))
@section('canonical', route('legal.withdrawal'))

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.legal_withdrawal_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.legal_withdrawal_title') }}</h2>
        </header>

        <div class="legal-page">
            @unless ($company->isComplete())
                <p class="legal-notice">
                    Certaines informations de cette page sont encore des espaces réservés. Complétez-les dans
                    <a href="{{ route('admin.settings.company.edit') }}">l'administration → Réglages → Company &amp; legal</a>.
                </p>
            @endunless
            <p class="legal-updated">Dernière mise à jour : {{ now()->translatedFormat('d F Y') }}</p>

            <h2>Délai de rétractation</h2>
            <p>
                Conformément aux articles L221-18 et suivants du Code de la consommation, le Client dispose d'un
                délai de 14 jours francs à compter de la réception du produit pour exercer son droit de rétractation,
                sans avoir à justifier de motif ni à payer de pénalité.
            </p>

            <h2>Exercice du droit de rétractation</h2>
            <p>
                Pour exercer ce droit, le Client doit notifier sa décision de rétractation par une déclaration dénuée
                d'ambiguïté, par e-mail à {{ $company->value('contact_email') }} ou par courrier à
                {{ $company->value('return_address') }}, avant l'expiration du délai de 14 jours. Le Client peut
                utiliser le formulaire type ci-dessous.
            </p>

            <h2>Modalités de retour</h2>
            <p>
                Le Client dispose de 14 jours à compter de la communication de sa décision de rétractation pour
                renvoyer ou restituer le produit, en l'état, dans son emballage d'origine si possible. Les frais de
                retour restent à la charge du Client, sauf mention contraire.
            </p>

            <h2>Remboursement</h2>
            <p>
                Le remboursement intervient dans un délai de 14 jours à compter de la réception du produit retourné
                (ou de la preuve d'expédition), par le même moyen de paiement que celui utilisé lors de la commande,
                sauf accord exprès du Client pour un autre moyen.
            </p>

            <h2>Exceptions au droit de rétractation</h2>
            <p>Le droit de rétractation ne peut être exercé, notamment, pour :</p>
            <ul>
                <li>les produits scellés ne pouvant être renvoyés pour des raisons d'hygiène ou de protection de la
                    santé, et qui ont été descellés après la livraison ;</li>
                <li>les produits personnalisés ou confectionnés selon les spécifications du Client ;</li>
                <li>les produits susceptibles de se détériorer ou de se périmer rapidement.</li>
            </ul>

            <h2>Formulaire type de rétractation</h2>
            <p>(à compléter et renvoyer uniquement en cas de volonté de se rétracter)</p>
            <p>
                À l'attention de {{ $company->value('company_name') }}, {{ $company->value('return_address') }},
                {{ $company->value('contact_email') }} :<br>
                Je notifie par la présente ma rétractation du contrat portant sur la commande n° [numéro de commande],
                commandée le [date de commande] et reçue le [date de réception].<br>
                Nom du Client : ……………………………<br>
                Adresse du Client : ……………………………<br>
                Signature du Client (en cas d'envoi papier) : ……………………………<br>
                Date : ……………………………
            </p>
        </div>
    </div>
@endsection
