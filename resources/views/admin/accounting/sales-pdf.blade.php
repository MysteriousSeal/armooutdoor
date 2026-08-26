{{--
    A month's journal of sales.

    Ten columns, so the widths below are tight and tuned against real data:
    changing one shifts every column after it. The rows come from
    AccountingController::journalData(), the same ones the screen shows.
--}}
@extends('admin.accounting.journal-pdf')

@section('title', 'Journal des ventes')

@push('styles')
        /* The journal itself. Column widths total 98%, leaving a margin; they
           are set per column because the renderer will otherwise share the
           width by content and wrap a long client name. */
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

        /* The totals row: heavier rule above, and its own headings, so the
           bottom of a long page reads without going back up. */
        table.journal tfoot td {
            padding: 9px 4px;
            font-size: 10.5px;
            font-weight: bold;
            border-top: 1.5px solid #8b7e74;
        }
        table.journal tfoot .perceived { font-size: 12px; }
        .foot-note { font-weight: normal; font-size: 9px; color: #6b6b6b; }
@endpush

@section('table')
    <table class="journal">
        {{-- Date, invoice, client, channel, kind, the three amounts, payment. --}}
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
        {{-- Orders and hand-written entries alike, already sorted by date. --}}
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
        {{-- The month's totals. Refunds are printed above but counted here. --}}
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
@endsection

@section('note')
    Les frais retenus sont la commission de la place de marché et les frais d'encaissement. Le port payé par la boutique est une dépense propre et n'est pas déduit ici. Les commandes remboursées figurent au journal mais n'entrent dans aucun total.
@endsection
