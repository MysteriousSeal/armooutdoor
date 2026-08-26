{{--
    A month's journal of purchases, for the accounting book.

    The counterpart of the sales journal and dressed the same way, so the two
    file together: one page per month, landscape, written in French.

    The lines come from AccountingController::purchaseJournalData(), the same
    ones the screen shows. Each is recorded as its invoice reads — the total
    paid and the rate charged — and the amount before tax and the tax itself
    are worked back from those.

    Every style is inline in this file. The PDF renderer has no access to the
    site's stylesheets, and the widths below are tuned against real data —
    changing one shifts every column after it.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Journal des achats {{ \App\Support\AccountingPeriods::key($period) }}</title>
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

        /* The three boxes under the title: period, entry count, print date. */
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

        /* The journal itself. Column widths total 98%, leaving a margin; they
           are set per column because the renderer will otherwise share the
           width by content and wrap a long client name. */
        /* The journal itself. Column widths total 98%, leaving a margin; they
           are set per column because the renderer will otherwise share the
           width by content and wrap a long supplier name. */
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
        .col-date { width: 9%; white-space: nowrap; }
        .col-invoice { width: 15%; }
        .col-supplier { width: 18%; }
        .col-type { width: 15%; }
        .col-money { width: 10%; }
        /* The rate explains the tax beside it without weighing as much. */
        .rate { margin-left: 3px; font-size: 8px; color: #8b7e74; }
        /* The payment ends at the right edge of the table, the way the date
           starts at the left one. That alignment is what sets it apart from
           the amounts. */
        .col-payment { width: 12%; text-align: right; }

        /* The totals row: heavier rule above, and its own headings, so the
           bottom of a long page reads without going back up. */
        table.journal tfoot td {
            padding: 9px 4px;
            font-size: 10.5px;
            font-weight: bold;
            border-top: 1.5px solid #8b7e74;
        }
        table.journal tfoot .paid { font-size: 12px; }
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
    {{-- Letterhead: the company, top left, where a letterhead belongs. --}}
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
            <td><div class="title">Journal des achats</div></td>
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
        {{-- Date, invoice, supplier, kind, the three amounts, payment. --}}
        <thead>
            <tr>
                <td class="col-date">Date</td>
                <td class="col-invoice">Facture</td>
                <td class="col-supplier">Fournisseur</td>
                <td class="col-type">Nature</td>
                <td class="col-money num">Total HT</td>
                <td class="col-money num">TVA</td>
                <td class="col-money num">Total TTC</td>
                <td class="col-payment">Règlement</td>
            </tr>
        </thead>
        {{-- Hand-written lines, already sorted by date. --}}
        <tbody>
            @foreach ($rows as $entry)
                <tr>
                    <td class="col-date">{{ $entry->entered_on->format('d/m/Y') }}</td>
                    <td class="col-invoice">{{ $entry->invoice_number ?: '—' }}</td>
                    <td class="col-supplier">{{ $entry->client ?: '—' }}</td>
                    <td class="col-type">{{ $entry->type ?: '—' }}</td>
                    <td class="col-money num">{{ format_euros($entry->exVatCents()) }}</td>
                    <td class="col-money num">
                        {{ format_euros($entry->vatCents()) }}
                        @if ($entry->vatRatePercent() !== null)
                            <span class="rate">{{ rtrim(rtrim(number_format($entry->vatRatePercent(), 1), '0'), '.') }}%</span>
                        @endif
                    </td>
                    <td class="col-money num">{{ format_euros($entry->total_cents) }}</td>
                    <td class="col-payment">{{ $entry->paymentLabelFr() }}</td>
                </tr>
            @endforeach
        </tbody>
        {{-- The month's totals. --}}
        <tfoot>
            <tr>
                <td colspan="4">Total du mois</td>
                <td class="num"><span class="foot-label">Total HT</span>{{ format_euros($exVatCents) }}</td>
                <td class="num"><span class="foot-label">TVA</span>{{ format_euros($vatCents) }}</td>
                <td class="num paid"><span class="foot-label">Total TTC</span>{{ format_euros($totalCents) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="notes-body">
        Chaque ligne est enregistrée telle que sa facture la donne : le total réglé et le taux appliqué. Le montant hors taxe et la taxe elle-même en sont déduits, de sorte qu'une ligne retombe toujours sur le papier dont elle vient.
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
        Journal des achats — {{ \App\Support\AccountingPeriods::labelFr($period) }}
    </div>
</body>
</html>
