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
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $entry)
                        <tr>
                            <td class="accounting-date">{{ $entry->entered_on->format('d/m/Y') }}</td>
                            <td><span class="admin-table-strong">{{ $entry->invoice_number ?: '—' }}</span></td>
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
                            {{-- Every line here was typed, so every line can be corrected. --}}
                            <td class="accounting-row-actions">
                                <button
                                    type="button"
                                    class="accounting-row-btn"
                                    data-modal-open="entry-modal"
                                    data-entry-edit
                                    data-entry-action="{{ route('admin.accounting.entries.update', ['section' => 'purchases', 'month' => $monthKey, 'entry' => $entry]) }}"
                                    @php
                                        $entryPayload = [
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
                                    data-entry='@json($entryPayload)'
                                    aria-label="Edit this entry"
                                    title="Edit this entry"
                                >
                                    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                                        <path d="M4 20h4L19 9a2 2 0 0 0-3-3L5 17z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="accounting-row-btn is-danger"
                                    data-modal-open="entry-delete-modal"
                                    data-entry-delete
                                    data-entry-action="{{ route('admin.accounting.entries.destroy', ['section' => 'purchases', 'month' => $monthKey, 'entry' => $entry]) }}"
                                    data-entry-label="{{ $entry->invoice_number ?: $entry->type }}"
                                    aria-label="Delete this entry"
                                    title="Delete this entry"
                                >
                                    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                                        <path d="M5 7h14M10 7V5h4v2m-8 0 1 13h10l1-13" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
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
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="accounting-note">
            Each line is recorded as its invoice reads: the total paid and the rate charged. The amount before tax and the tax itself are worked back from those, so a line always adds up to the paper it came from.
        </p>
    @endif

    @include('admin.accounting.partials.entry-modal', [
        'section' => 'purchases',
        'monthKey' => $monthKey,
        'period' => $period,
        'paymentMethods' => $paymentMethods,
    ])
</div>
