{{--
    One month of sales.

    The table mixes the shop's own orders with the entries typed by hand,
    `$rows` carrying both in one shape (see AccountingController::rowsOf).

    Holds the section end to end: the two buttons and what they say about the
    last copy taken out, the month's totals, its lines, and the form behind
    them.
--}}
<div class="admin-list-page accounting-month-page">
    <header class="admin-list-hero">
        <div class="admin-list-hero-row">
            @include('admin.accounting.partials.heading')
            <div class="accounting-hero-actions">
                <div class="accounting-hero-buttons">
                    {{-- The button stays in place, switched off: a month
                         still taking money in cannot be ruled off, and a
                         month with no line has nothing to print. --}}
                    @if ($downloadable)
                        <a href="{{ route('admin.accounting.sales.pdf', ['month' => $monthKey]) }}" class="btn btn-secondary">Download PDF</a>
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
            // A refund stays in the table — it happened — but adds to
            // nothing: the money went back out.
            $counted = $rows->where('counts', true);
            $refunded = $rows->count() - $counted->count();
            $totalCents = $counted->sum('total_cents');
            $feesCents = $counted->sum('fees_cents');
        @endphp

        @if ($rows->isEmpty())
            <p class="empty-state">No sales this month.</p>
        @else
            <div class="admin-stat-grid accounting-kpis">
                <div class="admin-stat-card">
                    <span class="admin-stat-label">Total</span>
                    <span class="admin-stat-value">{{ format_euros($totalCents) }}</span>
                </div>
                <div class="admin-stat-card">
                    <span class="admin-stat-label">Fees</span>
                    <span class="admin-stat-value">{{ $feesCents > 0 ? '−'.format_euros($feesCents) : '—' }}</span>
                </div>
                <div class="admin-stat-card">
                    <span class="admin-stat-label">Perceived</span>
                    <span class="admin-stat-value accounting-perceived">{{ format_euros($totalCents - $feesCents) }}</span>
                </div>
            </div>

            {{-- The month's lines, oldest first, orders and entries together. --}}
            <div class="admin-table-wrap accounting-journal">
                <table class="admin-table accounting-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Channel</th>
                            <th>Type</th>
                            <th class="admin-table-num">Total</th>
                            <th class="admin-table-num">Fees</th>
                            <th class="admin-table-num">Perceived</th>
                            <th>Payment</th>
                            <th>Remark</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="{{ $row['refunded'] ? 'is-refunded' : '' }} {{ $row['kind'] === 'entry' ? 'is-manual' : '' }}">
                                <td class="accounting-date">{{ $row['date']->format('d/m/Y') }}</td>
                                <td>
                                    <span class="accounting-invoice">
                                        @if ($row['kind'] === 'order')
                                            <a href="{{ route('admin.orders.show', $row['order']) }}" class="admin-table-strong">{{ $row['invoice'] }}</a>
                                        @else
                                            <span class="admin-table-strong">{{ $row['invoice'] }}</span>
                                            <span class="order-chip order-chip--manual" title="Entered by hand">Manual</span>
                                        @endif
                                    </span>
                                </td>
                                <td>{{ $row['client'] }}</td>
                                <td><span class="order-chip order-chip--channel">{{ $row['channel'] }}</span></td>
                                <td><span class="order-chip">{{ $row['type'] }}</span></td>
                                <td class="admin-table-num">{{ format_euros($row['total_cents']) }}</td>
                                <td class="admin-table-num">{{ $row['fees_cents'] > 0 ? '−'.format_euros($row['fees_cents']) : '—' }}</td>
                                <td class="admin-table-num">{{ format_euros($row['total_cents'] - $row['fees_cents']) }}</td>
                                <td><span class="order-chip">{{ $row['payment'] }}</span></td>
                                <td class="accounting-remark">{{ $row['remark'] }}</td>
                                {{-- Edit and delete, on hand-written lines only:
                                     an order is corrected on the order itself. --}}
                                <td class="accounting-row-actions">
                                    @if ($row['kind'] === 'entry')
                                        @php
                                            $entry = $row['entry'];
                                            // The form reads these values to fill itself in.
                                            $entryPayload = [
                                                'entered_on' => $entry->entered_on->format('Y-m-d'),
                                                'invoice_number' => $entry->invoice_number,
                                                'client' => $entry->client,
                                                'channel' => $entry->channel,
                                                'type' => $entry->type,
                                                'total' => number_format($entry->total_cents / 100, 2, '.', ''),
                                                'fees' => number_format($entry->fees_cents / 100, 2, '.', ''),
                                                'payment_method' => $entry->payment_method,
                                                'remark' => $entry->remark,
                                            ];
                                        @endphp
                                        <button
                                            type="button"
                                            class="accounting-row-btn"
                                            data-modal-open="entry-modal"
                                            data-entry-edit
                                            data-entry-action="{{ route('admin.accounting.entries.update', ['section' => $section, 'month' => $monthKey, 'entry' => $entry]) }}"
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
                                            data-entry-action="{{ route('admin.accounting.entries.destroy', ['section' => $section, 'month' => $monthKey, 'entry' => $entry]) }}"
                                            data-entry-label="{{ $entry->invoice_number ?: $entry->typeLabel() }}"
                                            aria-label="Delete this entry"
                                            title="Delete this entry"
                                        >
                                            <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                                                <path d="M5 7h14M10 7V5h4v2m-8 0 1 13h10l1-13" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    {{-- The month's totals, refunds left out of all three. --}}
                    <tfoot>
                        <tr>
                            <td colspan="5">
                                {{ trans_choice('{0}no sale|{1}:count sale|[2,*]:count sales', $counted->count(), ['count' => $counted->count()]) }}
                                @if ($refunded > 0)
                                    <span class="accounting-foot-note">{{ trans_choice('{1}:count refund left out|[2,*]:count refunds left out', $refunded, ['count' => $refunded]) }}</span>
                                @endif
                            </td>
                            <td class="admin-table-num">{{ format_euros($totalCents) }}</td>
                            <td class="admin-table-num">{{ $feesCents > 0 ? '−'.format_euros($feesCents) : '—' }}</td>
                            <td class="admin-table-num accounting-perceived">{{ format_euros($totalCents - $feesCents) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Says out loud what the figures leave out, so the totals are
                 not taken for something they are not. --}}
            <p class="accounting-note">
                Fees are the marketplace commission and the payment charge. Shipping paid out of pocket is a cost of its own and is not deducted here. Refunded orders are listed but left out of every total.
            </p>
        @endif

        @include('admin.accounting.partials.entry-modal', [
            'section' => 'sales',
            'monthKey' => $monthKey,
            'period' => $period,
            'entryTypes' => $entryTypes,
            'paymentMethods' => $paymentMethods,
        ])
</div>
