{{--
    One month of purchases: what the shop paid out.

    Holds the section end to end: the buttons, the month's totals, its lines
    and the form behind them. Hand-written lines only. Each is recorded as the supplier's invoice reads —
    the total paid and the rate — and the two other figures are worked back
    from it, so the sheet always agrees with the paper it came from.
--}}
<div class="admin-list-page accounting-month-page">
    <header class="admin-list-hero">
        <div class="admin-list-hero-row">
            @include('admin.accounting.partials.heading')
            {{-- The same two buttons in the same corner as on a month of sales:
                 the pages are read one after the other, and a button that moves
                 has to be looked for. --}}
            <div class="accounting-hero-actions">
                <div class="accounting-hero-buttons">
                    {{-- Switched off while the month still runs, and on a month
                         that bought nothing: an empty sheet is not a document. --}}
                    @if ($downloadable)
                        <a href="{{ route('admin.accounting.purchases.pdf', ['month' => $monthKey]) }}" class="btn btn-secondary">Download PDF</a>
                    @else
                        <span
                            class="btn btn-secondary is-disabled"
                            aria-disabled="true"
                            title="{{ \App\Support\AccountingPeriods::isClosed($period) ? 'Nothing to print for this month' : 'Available once the month has ended' }}"
                        >Download PDF</span>
                    @endif
                    <button type="button" class="btn btn-primary" data-modal-open="entry-modal" data-entry-new>Add entry</button>
                </div>

                @include('admin.accounting.partials.download-note')
            </div>
        </div>
    </header>

    @php
        $exVatCents = $rows->sum(fn ($entry) => $entry->exVatCents());
        $vatCents = $rows->sum(fn ($entry) => $entry->vatCents());
        $totalCents = $rows->sum('total_cents');
        // Lines whose invoice exists on paper but has not been attached. A line
        // with no invoice number is left out: nothing can be attached to it, so
        // counting it would show a figure that never reaches zero.
        $missingInvoices = $rows->filter(fn ($entry) => $entry->isMissingInvoiceFile())->count();
    @endphp

    @if ($rows->isEmpty())
        <p class="empty-state">No purchases this month.</p>
    @else
        {{-- Ex-VAT first: it is the cost. The tax beside it is what comes back. --}}
        <div class="admin-stat-grid accounting-kpis">
            <div class="admin-stat-card">
                <span class="admin-stat-label">Total excl. VAT</span>
                <span class="admin-stat-value">{{ format_euros($exVatCents) }}</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-label">VAT</span>
                <span class="admin-stat-value">{{ format_euros($vatCents) }}</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-label">Total incl. VAT</span>
                <span class="admin-stat-value">{{ format_euros($totalCents) }}</span>
            </div>
            {{-- What is still owed to the file, not a figure of money: a month
                 whose paperwork is complete says so rather than showing a bare
                 zero to interpret. --}}
            <div class="admin-stat-card accounting-missing-card {{ $missingInvoices === 0 ? 'is-complete' : '' }}">
                <span class="admin-stat-label">Invoices missing</span>
                @if ($missingInvoices === 0)
                    <span class="admin-stat-value accounting-missing-none">All attached</span>
                @else
                    <span class="admin-stat-value">{{ $missingInvoices }}</span>
                    <span class="accounting-missing-note">{{ trans_choice('{1}line without its PDF|[2,*]lines without their PDF', $missingInvoices) }}</span>
                @endif
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table accounting-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Supplier</th>
                        <th>Type</th>
                        <th class="admin-table-num">Excl. VAT</th>
                        <th class="admin-table-num">VAT</th>
                        <th class="admin-table-num">Incl. VAT</th>
                        <th>Payment</th>
                        <th>Remark</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $entry)
                        <tr>
                            <td class="accounting-date">{{ $entry->entered_on->format('d/m/Y') }}</td>
                            <td>
                                <span class="admin-table-strong">{{ $entry->invoice_number ?: '—' }}</span>
                                {{-- The paper behind the line, attached to the
                                     number it belongs to. --}}
                                <span class="accounting-invoice-file">
                                    @if ($entry->hasInvoiceFile())
                                        <a
                                            href="{{ route('admin.accounting.entries.invoice.show', ['section' => 'purchases', 'month' => $monthKey, 'entry' => $entry]) }}"
                                            class="accounting-invoice-link"
                                            target="_blank"
                                            rel="noopener"
                                            title="Open {{ $entry->invoiceFileName() }}"
                                        >
                                            <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
                                                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                <path d="M14 3v5h5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                            </svg>
                                            PDF
                                        </a>
                                        {{-- Asks first: a detached invoice is gone from
                                             the disk, and the paper may not be
                                             anywhere else. --}}
                                        <button
                                            type="button"
                                            class="accounting-invoice-detach"
                                            data-modal-open="invoice-delete-modal"
                                            data-invoice-delete
                                            data-invoice-action="{{ route('admin.accounting.entries.invoice.destroy', ['section' => 'purchases', 'month' => $monthKey, 'entry' => $entry]) }}"
                                            data-invoice-label="{{ $entry->invoice_number ?: $entry->entered_on->format('d/m/Y') }}"
                                            title="Remove this invoice"
                                            aria-label="Remove the invoice attached to this line"
                                        >&times;</button>
                                    @elseif ($entry->acceptsInvoiceFile())
                                        {{-- A label rather than a button: the file
                                             picker is the input itself, and the form
                                             sends as soon as a file is chosen. Only
                                             offered where there is paper to attach. --}}
                                        <form
                                            method="POST"
                                            action="{{ route('admin.accounting.entries.invoice.store', ['section' => 'purchases', 'month' => $monthKey, 'entry' => $entry]) }}"
                                            class="accounting-invoice-form"
                                            enctype="multipart/form-data"
                                        >
                                            @csrf
                                            <label class="accounting-invoice-attach">
                                                <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
                                                    <path d="M21 11.5 12.5 20a5 5 0 0 1-7-7l8-8a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7.5-7.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                Attach
                                                <input type="file" name="invoice_file" accept="application/pdf" data-invoice-file hidden>
                                            </label>
                                        </form>
                                    @endif
                                </span>
                            </td>
                            <td>{{ $entry->client ?: '—' }}</td>
                            <td>{{ $entry->type ?: '—' }}</td>
                            <td class="admin-table-num">{{ format_euros($entry->exVatCents()) }}</td>
                            <td class="admin-table-num">
                                {{ format_euros($entry->vatCents()) }}
                                @if ($entry->vatRatePercent() !== null)
                                    <span class="accounting-vat-rate">{{ rtrim(rtrim(number_format($entry->vatRatePercent(), 1), '0'), '.') }}%</span>
                                @endif
                            </td>
                            <td class="admin-table-num">{{ format_euros($entry->total_cents) }}</td>
                            <td>{{ $entry->paymentLabel() }}</td>
                            <td class="accounting-remark">{{ $entry->remark }}</td>
                            {{-- Every line here was typed, so every line can be corrected. --}}
                            <td class="accounting-row-actions">
                                @php
                                    // The form reads these values to fill itself in.
                                    $payload = [
                                        'entered_on' => $entry->entered_on->format('Y-m-d'),
                                        'invoice_number' => $entry->invoice_number,
                                        'client' => $entry->client,
                                        'type' => $entry->type,
                                        'total' => number_format($entry->total_cents / 100, 2, '.', ''),
                                        'vat_rate' => rtrim(rtrim(number_format($entry->vatRatePercent() ?? 0, 2, '.', ''), '0'), '.'),
                                        'payment_method' => $entry->payment_method,
                                        'remark' => $entry->remark,
                                    ];
                                @endphp
                                @include('admin.accounting.partials.row-actions', [
                                    'label' => $entry->invoice_number ?: $entry->type,
                                ])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">{{ trans_choice('{1}:count purchase|[0,*]:count purchases', $rows->count(), ['count' => $rows->count()]) }}</td>
                        <td class="admin-table-num">{{ format_euros($exVatCents) }}</td>
                        <td class="admin-table-num">{{ format_euros($vatCents) }}</td>
                        <td class="admin-table-num accounting-perceived">{{ format_euros($totalCents) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="accounting-note">
            For each line you enter the total incl. VAT and the VAT rate, both taken from the supplier's invoice. The amount excl. VAT and the VAT are calculated from those two figures.
        </p>
    @endif

    {{-- Detaching an invoice asks first, like every other removal in the admin. --}}
<dialog id="invoice-delete-modal" class="modal" aria-labelledby="invoice-delete-title">
    <form method="POST" id="invoice-delete-form">
        @csrf
        @method('DELETE')
        <h3 class="modal-title" id="invoice-delete-title">Remove this invoice?</h3>
        <p class="modal-body">
            The invoice attached to <span id="invoice-delete-label"></span> will be deleted.
            The purchase itself stays on the month.
        </p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-danger">Remove</button>
        </div>
    </form>
</dialog>

@include('admin.accounting.partials.entry-modal', [
        'section' => 'purchases',
        'monthKey' => $monthKey,
        'period' => $period,
        'paymentMethods' => $paymentMethods,
    ])
</div>
