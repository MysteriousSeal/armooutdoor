{{--
    A month's journal of sales, for the accounting book.

    One page per month, landscape: ten columns do not stand upright without
    cutting names in half. Dressed like the other documents of the house.
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
        /* Amounts on the right, in a column: that is how they are added up by
           eye, and the footer falls right underneath. */
        .num { text-align: right; white-space: nowrap; }
        .col-date { width: 8%; white-space: nowrap; }
        .col-invoice { width: 13%; }
        .col-client { width: 15%; }
        .col-channel { width: 11%; }
        .col-type { width: 10%; }
        .col-money { width: 9%; }
        /* The perceived figure is the one you come looking for: a little
           wider than its neighbours. */
        .col-money-wide { width: 11%; }
        /* The payment ends at the right edge of the table, the way the date
           starts at the left one. That alignment is what sets it apart from
           the perceived figure — widening that column only pushed its number
           closer to the word, and dompdf ignores padding declared on a table
           cell, with or without !important. */
        .col-payment { width: 12%; text-align: right; }

        /* A refunded line stays in the journal — it happened — but adds to
           nothing. The struck-through invoice says so; a label beside the
           strike would say it twice. */
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
        /* The heading above a total, in small type: it recalls the column
           without weighing more than the figure. */
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

        /* The signature, at the foot of the page. A frame rather than three
           bare rules: a missing signature has to show at a glance on a filed
           sheet, without reading the labels. */
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
        /* Tall enough for a real handwritten signature, not a paraph. */
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
    {{-- The journal belongs to the company, not to the shop sign: SwiftShelf
         keeps the accounts, ArmoOutdoor is only the shop. --}}
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
                            {{-- The company address, not the shop's: the journal is
                                 a SwiftShelf document, and the shop contact can
                                 change in the settings without the accounting
                                 book following. --}}
                            hello@swiftshelf.fr
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
                {{-- The headings come back at the foot: on a long page, the
                     bottom of the table reads without going back up. --}}
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
