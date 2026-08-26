{{--
    Le journal des ventes d'un mois, pour le livre de comptes.

    Une page par mois, en paysage : dix colonnes ne tiennent pas debout sans
    couper les noms. Même habillage que les autres documents de la maison.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Journal des ventes {{ \App\Support\AccountingPeriods::key($period) }}</title>
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
        .company-info { font-size: 9.5px; line-height: 1.4; color: #6b6b6b; }
        .company-info .name { margin-bottom: 2px; font-size: 11px; font-weight: bold; color: #2c2c2c; }
        table.company-cols { border-collapse: collapse; }
        table.company-cols td { vertical-align: top; }
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

        table.journal { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.journal thead td {
            padding: 6px 4px;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #8b7e74;
            border-bottom: 1px solid #8b7e74;
        }
        table.journal tbody td {
            padding: 6px 4px;
            font-size: 9.5px;
            vertical-align: top;
            border-bottom: 1px solid #e8e6e3;
        }
        /* Les montants à droite, en colonne : c'est ainsi qu'on les additionne
           du regard, et le pied tombe pile dessous. */
        .num { text-align: right; white-space: nowrap; }
        .col-date { width: 8%; white-space: nowrap; }
        .col-invoice { width: 13%; }
        .col-client { width: 12%; }
        .col-channel { width: 11%; }
        .col-type { width: 10%; }
        .col-money { width: 9%; }
        /* Le perçu est le chiffre qu'on vient chercher. Aligné à droite, il
           touchait le bord de sa colonne, donc le mot d'à côté : le retrait
           de droite le ramène vers l'intérieur. Élargir la colonne ne
           servait à rien, cela poussait le chiffre encore plus à droite. */
        .col-money-wide { width: 11%; }
        /* Le règlement a de la place de reste : le mot commence plus loin
           plutôt que de coller au perçu. C'est le retrait qui sépare les
           deux, pas un filet — un trait de plus dans un tableau déjà réglé
           se lirait comme une colonne coupée en deux. */
        /* Sélecteur plus fort que `table.journal tbody td`, qui pose le
           padding de toutes les cellules : sans cela le retrait était écrasé
           et le mot restait collé au chiffre. */
        /* Le règlement finit au bord droit du tableau, comme la date
           commence au bord gauche. L'écart avec le perçu vient de là : le
           mot se range à la fin plutôt que de se coller au chiffre. */
        .col-payment { width: 15%; text-align: right; }

        /* Une ligne remboursée reste au journal — elle a eu lieu — mais ne
           s'ajoute à rien. Barrée, elle se lit comme telle. */
        tr.refunded td { color: #8b7e74; }
        tr.refunded .col-invoice { text-decoration: line-through; }
        .tag {
            margin-left: 4px;
            padding: 1px 4px;
            font-size: 7.5px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #6b6b6b;
            border: 1px solid #e8e6e3;
        }

        table.journal tfoot td {
            padding: 9px 4px;
            font-size: 10.5px;
            font-weight: bold;
            border-top: 1.5px solid #8b7e74;
        }
        table.journal tfoot .perceived { font-size: 12px; }
        .foot-note { font-weight: normal; font-size: 9px; color: #6b6b6b; }
        /* L'intitulé au-dessus du total, en petit : il rappelle la colonne
           sans peser plus que le chiffre. */
        .foot-label {
            display: block;
            margin-bottom: 2px;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8b7e74;
        }

        .notes-body { margin-bottom: 12px; font-size: 9.5px; line-height: 1.5; color: #6b6b6b; }

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
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e8e6e3;
            text-align: center;
            font-size: 9px;
            color: #6b6b6b;
        }
    </style>
</head>
<body>
    {{-- Le journal appartient à la société, pas à l'enseigne : c'est
         SwiftShelf qui tient les comptes, ArmoOutdoor n'est que la
         boutique. --}}
    <table class="header">
        <tr>
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
            <td><div class="title">Journal des ventes</div></td>
            <td class="title-meta">{{ \App\Support\AccountingPeriods::labelFr($period) }}</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Période</div>
                <div class="value">{{ \App\Support\AccountingPeriods::dateFr($period, false) }} au {{ \App\Support\AccountingPeriods::dateFr($period->endOfMonth()) }}</div>
            </td>
            <td>
                <div class="label">Écritures</div>
                <div class="value">{{ $rows->count() }}</div>
            </td>
            <td>
                <div class="label">Édité le</div>
                <div class="value">{{ \App\Support\AccountingPeriods::dateFr(\Carbon\CarbonImmutable::now()) }}</div>
            </td>
        </tr>
    </table>

    <table class="journal">
        <thead>
            <tr>
                <td class="col-date">Date</td>
                <td class="col-invoice">Facture</td>
                <td class="col-client">Client</td>
                <td class="col-channel">Canal</td>
                <td class="col-type">Nature</td>
                <td class="col-money num">Total</td>
                <td class="col-money num">Frais</td>
                <td class="col-money-wide num">Perçu</td>
                <td class="col-payment">Règlement</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="{{ $row['refunded'] ? 'refunded' : '' }}">
                    <td class="col-date">{{ $row['date']->format('d/m/Y') }}</td>
                    <td class="col-invoice">
                        {{ $row['invoice'] }}
                        @if ($row['kind'] === 'entry')
                            <span class="tag">Saisie</span>
                        @endif
                        @if ($row['refunded'])
                            <span class="tag">Remboursé</span>
                        @endif
                    </td>
                    <td class="col-client">{{ $row['client'] }}</td>
                    <td class="col-channel">{{ $row['channel'] }}</td>
                    <td class="col-type">{{ $row['type_fr'] }}</td>
                    <td class="col-money num">{{ format_euros($row['total_cents']) }}</td>
                    <td class="col-money num">{{ $row['fees_cents'] > 0 ? '−'.format_euros($row['fees_cents']) : '—' }}</td>
                    <td class="col-money-wide num">{{ format_euros($row['total_cents'] - $row['fees_cents']) }}</td>
                    <td class="col-payment">{{ $row['payment_fr'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">
                    Total du mois
                    @if ($refunded > 0)
                        <span class="foot-note">— {{ $refunded }} remboursement{{ $refunded > 1 ? 's' : '' }} hors total</span>
                    @endif
                </td>
                {{-- Les intitulés reviennent au pied : sur une page longue, le
                     bas du tableau se lit sans remonter à l'en-tête. --}}
                <td class="num"><span class="foot-label">Total</span>{{ format_euros($totalCents) }}</td>
                <td class="num"><span class="foot-label">Frais</span>{{ $feesCents > 0 ? '−'.format_euros($feesCents) : '—' }}</td>
                <td class="num perceived col-money-wide"><span class="foot-label">Perçu</span>{{ format_euros($totalCents - $feesCents) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="notes-body">
        Les frais retenus sont la commission de la place de marché et les frais d'encaissement. Le port payé par la boutique est une dépense propre et n'est pas déduit ici. Les commandes remboursées figurent au journal mais n'entrent dans aucun total.
    </div>

    <table class="signature">
        <tr>
            <td colspan="3" style="border-bottom: 0; background: #fff; padding-bottom: 2px;">
                <div class="sign-label">Signature</div>
                <div class="sign-scope">Pour {{ $company->value('company_name') }}</div>
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
        Journal des ventes — {{ \App\Support\AccountingPeriods::labelFr($period) }}
    </div>
</body>
</html>
