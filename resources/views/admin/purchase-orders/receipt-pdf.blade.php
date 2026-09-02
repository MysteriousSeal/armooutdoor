{{--
    Le bon de réception : la feuille qu'on tient à la main en ouvrant les
    colis.

    Pas de prix, pas de totaux — ce n'est pas ce qu'on vérifie une caisse
    ouverte. Les cases partent vides quel que soit l'état de la commande dans
    l'outil : une case déjà cochée invite à faire confiance au papier plutôt
    qu'à regarder dans le carton.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de réception {{ $purchaseOrder->number }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11.5px;
            line-height: 1.45;
            color: #2c2c2c;
            padding: 48px 56px;
        }

        .header { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .header td { vertical-align: top; }
        .brand { font-size: 20.5px; letter-spacing: -0.03em; }
        .brand-primary { font-weight: bold; color: #2c2c2c; }
        .brand-secondary { font-weight: normal; color: #6b6b6b; }
        .brand-tag {
            margin-top: 4px;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        /* L'expéditeur en deux colonnes : l'adresse d'un côté, les moyens de
           le joindre de l'autre. Six lignes empilées mangeaient le haut de la
           page pour un bloc que personne ne lit ligne à ligne. */
        .company-info { text-align: right; font-size: 9.5px; line-height: 1.4; color: #6b6b6b; }
        .company-info .name { margin-bottom: 2px; font-size: 11px; font-weight: bold; color: #2c2c2c; }
        table.company-cols { margin-left: auto; border-collapse: collapse; }
        table.company-cols td { vertical-align: top; text-align: right; }
        table.company-cols td + td { padding-left: 16px; }

        .title-row { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .title-row td { vertical-align: bottom; }
        .title {
            font-size: 20.5px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #2c2c2c;
        }
        .title-meta { text-align: right; font-size: 10.5px; color: #6b6b6b; }

        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.meta td {
            width: 33.33%;
            padding: 8px 10px;
            background: #f7f6f4;
            border: 1px solid #e8e6e3;
        }
        table.meta td + td { border-left: 0; }
        table.meta .label {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        table.meta .value { padding-top: 3px; font-size: 11.5px; color: #2c2c2c; }

        table.parties { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.parties td { width: 50%; vertical-align: top; padding-right: 18px; }
        .addr-label {
            margin-bottom: 6px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .addr-body { font-size: 11.5px; line-height: 1.55; }
        .addr-body .strong { font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items thead td {
            padding: 7px 5px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8b7e74;
            border-bottom: 1px solid #8b7e74;
        }
        table.items thead td.col-check { text-align: center; }
        /* Les lignes respirent plus que sur le bon de commande : on écrit
           dessus, souvent debout, un carton dans l'autre main. */
        table.items tbody td {
            padding: 13px 5px;
            vertical-align: middle;
            border-bottom: 1px solid #e8e6e3;
        }
        .col-thumb { width: 42px; }
        .col-thumb img { width: 36px; height: 36px; }
        .col-name { font-size: 11.5px; }
        .line-detail { margin-top: 2px; font-size: 9.5px; color: #6b6b6b; }
        .col-qty { width: 8%; text-align: right; white-space: nowrap; font-weight: bold; }

        /* La case où l'on écrit le compte trouvé : un tiret coché ne dit pas
           qu'il en manque trois. */
        .col-count { width: 13%; text-align: center; }
        .count-box {
            width: 42px;
            height: 22px;
            margin: 0 auto;
            border: 1px solid #b9b2aa;
            background: #fbfaf9;
        }

        .col-check { width: 12%; text-align: center; }
        .check-box {
            width: 17px;
            height: 17px;
            margin: 0 auto;
            border: 1.5px solid #8b7e74;
        }

        .section-label {
            margin-bottom: 5px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .notes-body { margin-bottom: 14px; font-size: 10.5px; line-height: 1.55; color: #6b6b6b; }
        .notes-count { font-size: 11.5px; }

        /* De quoi signer et dater : une feuille de réception sans nom ni date
           ne prouve rien un mois plus tard. */
        table.signoff { width: 100%; border-collapse: collapse; margin-top: 26px; }
        table.signoff td { width: 33.33%; padding-right: 18px; vertical-align: top; }
        .signoff-line {
            height: 34px;
            margin-top: 4px;
            border-bottom: 1px solid #b9b2aa;
        }

        /* La signature, en bas de page. Un cadre plutôt que trois traits :
           on doit voir d'un coup d'œil s'il manque une signature sur une
           feuille classée, sans lire les intitulés. */
        table.signature { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.signature td {
            padding: 9px 12px 10px;
            border: 1px solid #e8e6e3;
            background: #f7f6f4;
            vertical-align: top;
        }
        table.signature td.sign-name { width: 34%; }
        table.signature td.sign-date { width: 22%; }
        table.signature td + td { border-left: 0; }
        .sign-label {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .sign-scope { margin-bottom: 7px; font-size: 10.5px; color: #2c2c2c; }
        /* Assez haut pour une vraie signature manuscrite, pas un paraphe. */
        .sign-rule { height: 46px; }
        .sign-rule-short { height: 22px; }

        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e8e6e3;
            text-align: center;
            font-size: 9px;
            color: #6b6b6b;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">
                    <span class="brand-primary">Armo</span><span class="brand-secondary">Outdoor</span>
                </div>
                <div class="brand-tag">Stand et terrain</div>
            </td>
            <td class="company-info">
                <div class="name">{{ $company->value('company_name') }}</div>
                <table class="company-cols">
                    <tr>
                        <td>
                            @foreach ($company->addressLines() as $line)
                                {{ $line }}<br>
                            @endforeach
                            SIRET {{ $company->value('siret') }}
                        </td>
                        <td>
                            {{ $company->formattedPhone() }}<br>
                            {{ $company->value('contact_email') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="title-row">
        <tr>
            <td><div class="title">Bon de réception</div></td>
            <td class="title-meta">{{ $purchaseOrder->number }} · {{ $purchaseOrder->created_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Commande</div>
                <div class="value">{{ $purchaseOrder->number }}</div>
            </td>
            <td>
                <div class="label">Fournisseur</div>
                <div class="value">{{ $purchaseOrder->supplier_name }}</div>
            </td>
            <td>
                <div class="label">Livraison souhaitée</div>
                <div class="value">{{ $purchaseOrder->expected_at?->translatedFormat('d F Y') ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="addr-label">Fournisseur</div>
                <div class="addr-body">
                    <span class="strong">{{ $purchaseOrder->supplier_name }}</span><br>
                    @if ($purchaseOrder->supplier?->website)
                        {{ $purchaseOrder->supplier->website }}<br>
                    @endif
                    @if ($purchaseOrder->reference)
                        Votre référence : {{ $purchaseOrder->reference }}
                    @endif
                </div>
            </td>
            <td>
                <div class="addr-label">Adresse de livraison</div>
                <div class="addr-body">
                    <span class="strong">{{ $company->value('company_name') }}</span><br>
                    @foreach ($company->addressLines() as $line)
                        {{ $line }}<br>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <td class="col-thumb"></td>
                <td class="col-name">Désignation</td>
                <td class="col-qty">Cdé</td>
                <td class="col-count">Reçu</td>
                <td class="col-check">Received</td>
                <td class="col-check">Handled</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td class="col-thumb">
                        @if ($item->imagePath())
                            <img src="{{ $item->imagePath() }}" alt="">
                        @endif
                    </td>
                    <td class="col-name">
                        {{ $item->name }}
                        @php
                            $details = array_filter([
                                $item->variant?->label(),
                                // The shop's own SKU, never the supplier's reference: the sheet
                                // is read next to the shop's shelves, not the supplier's.
                                $item->sku,
                            ]);
                        @endphp
                        @if ($details !== [])
                            <div class="line-detail">{{ implode(' · ', $details) }}</div>
                        @endif
                    </td>
                    <td class="col-qty">{{ $item->quantity_ordered }}</td>
                    <td class="col-count"><div class="count-box"></div></td>
                    <td class="col-check"><div class="check-box"></div></td>
                    <td class="col-check"><div class="check-box"></div></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($purchaseOrder->notes)
        <div class="section-label">Remarques</div>
        <div class="notes-body">{{ $purchaseOrder->notes }}</div>
    @endif

    <div class="section-label">Nombre d'articles commandés</div>
    <div class="notes-count">{{ $purchaseOrder->items->sum('quantity_ordered') }}</div>

    <table class="signoff">
        <tr>
            <td>
                <div class="section-label">Reçu le</div>
                <div class="signoff-line"></div>
            </td>
            <td>
                <div class="section-label">Par</div>
                <div class="signoff-line"></div>
            </td>
            <td>
                <div class="section-label">Observations</div>
                <div class="signoff-line"></div>
            </td>
        </tr>
    </table>

    <table class="signature">
        <tr>
            <td colspan="3" style="border-bottom: 0; background: #fff; padding-bottom: 2px;">
                <div class="sign-label">Signature</div>
                <div class="sign-scope">Pour ArmoOutdoor, {{ $company->value('company_name') }}</div>
            </td>
        </tr>
        <tr>
            <td class="sign-name">
                <div class="sign-label">Nom</div>
                <div class="sign-rule-short"></div>
            </td>
            <td class="sign-date">
                <div class="sign-label">Date</div>
                <div class="sign-rule-short"></div>
            </td>
            <td>
                <div class="sign-label">Signature</div>
                <div class="sign-rule"></div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Document interne — à reporter dans la fiche de la commande une fois les colis ouverts
    </div>
</body>
</html>
