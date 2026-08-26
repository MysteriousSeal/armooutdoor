{{--
    A month's journal of purchases.

    Eight columns, and a supplier name that must not be cut in half. The rows
    come from AccountingController::purchaseJournalData(), the same ones the
    screen shows.
--}}
@extends('admin.accounting.journal-pdf')

@section('title', 'Journal des achats')

@push('styles')
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
@endpush

@section('table')
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
@endsection

@section('note')
    Pour chaque ligne, on saisit le total TTC et le taux de TVA figurant sur la facture du fournisseur. Le total HT et la TVA sont calculés à partir de ces deux montants.
@endsection
