{{--
    One month of the accounts.

    Sales show a table mixing the shop's orders and the entries typed by hand,
    `$rows` carrying both in one shape (see AccountingController::rowsOf).
    Purchases share this template and show only the heading for now.

    Two dialogs live at the bottom: one form serving both adding and correcting
    an entry, and a confirmation before deleting one.
--}}
@extends('layouts.admin')

@section('title', $title.' — '.\App\Support\AccountingPeriods::label($period))

@section('content')
    @php
        $monthKey = \App\Support\AccountingPeriods::key($period);
    @endphp
    <div class="admin-list-page accounting-month-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">
                        <a href="{{ route('admin.accounting.'.$section) }}">{{ $title }}</a>
                    </p>
                    <h2 class="admin-list-title">{{ \App\Support\AccountingPeriods::label($period) }}</h2>
                    <p class="admin-list-lede">
                        {{ $period->locale('en')->isoFormat('D MMMM') }} to {{ $period->endOfMonth()->locale('en')->isoFormat('D MMMM YYYY') }}
                    </p>
                </div>
                @if ($section === 'sales')
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

                        {{-- Under the buttons, where the decision to take a
                             copy out is made. --}}
                        @if ($lastDownload && $stale)
                            {{-- The filed copy no longer matches the month:
                                 said plainly, since a journal that disagrees
                                 with the accounts is worse than none. --}}
                            <p class="accounting-download-note is-stale">
                                <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
                                    <path d="M12 8v5m0 3.5v.1M10.3 4.3 2.8 17.5A1.5 1.5 0 0 0 4.1 20h15.8a1.5 1.5 0 0 0 1.3-2.5L13.7 4.3a1.5 1.5 0 0 0-2.6 0z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span>Changed since the copy of {{ $lastDownload->created_at->format('j M Y') }} at {{ $lastDownload->created_at->format('H:i') }} — download it again</span>
                            </p>
                        @elseif ($lastDownload)
                            <p class="accounting-download-note is-filed">
                                <span>
                                    Last downloaded {{ $lastDownload->created_at->format('j M Y') }} at {{ $lastDownload->created_at->format('H:i') }}
                                    @if ($lastDownload->user)
                                        by {{ $lastDownload->user->name }}
                                    @endif
                                </span>
                            </p>
                        @else
                            <p class="accounting-download-note is-never">Never downloaded</p>
                        @endif
                    </div>
                @endif
            </div>
            @if ($section === 'sales')
                <div class="admin-list-meta">
                    @if (\App\Support\AccountingPeriods::isClosed($period))
                        <span class="admin-list-chip admin-list-chip--shipped">Closed</span>
                    @else
                        <span class="admin-list-chip">In progress</span>
                    @endif
                </div>
            @endif
        </header>

        @if ($section !== 'sales')
            <p class="empty-state">Nothing here yet.</p>
        @else
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

            {{-- One dialog for both jobs: the JS points the form at the right
                 URL and swaps the method (see admin-accounting-entry.js). --}}
            <dialog id="entry-modal" class="modal modal--wide" aria-labelledby="entry-modal-title">
                <form method="POST" id="entry-form" action="{{ route('admin.accounting.entries.store', ['section' => $section, 'month' => $monthKey]) }}">
                    @csrf
                    {{-- Swapped for PUT when editing; one form serves both. --}}
                    <input type="hidden" name="_method" id="entry-method" value="POST">

                    <h3 class="modal-title" id="entry-modal-title">Add an entry</h3>
                    <p class="modal-body">Anything sold outside a shop order: a prestation, a repair, a sale made by hand.</p>

                    {{-- The same columns the table prints, in the same order. --}}
                    <div class="entry-form-grid">
                        <div class="form-group">
                            <label for="entry-date">Date</label>
                            <input
                                type="date"
                                id="entry-date"
                                name="entered_on"
                                class="form-control"
                                value="{{ old('entered_on', $monthKey.'-01') }}"
                                min="{{ $period->format('Y-m-d') }}"
                                max="{{ $period->endOfMonth()->format('Y-m-d') }}"
                                required
                            >
                            @error('entered_on') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="entry-invoice">Invoice</label>
                            <input type="text" id="entry-invoice" name="invoice_number" class="form-control" value="{{ old('invoice_number') }}" maxlength="80" placeholder="INV-…">
                            @error('invoice_number') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="entry-client">Client</label>
                            <input type="text" id="entry-client" name="client" class="form-control" value="{{ old('client') }}" maxlength="120">
                            @error('client') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="entry-channel">Channel</label>
                            <input type="text" id="entry-channel" name="channel" class="form-control" value="{{ old('channel') }}" maxlength="80" placeholder="Direct">
                            @error('channel') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="entry-type">Type</label>
                            <select id="entry-type" name="type" class="form-control" required>
                                @foreach ($entryTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="entry-payment">Payment</label>
                            <select id="entry-payment" name="payment_method" class="form-control" required>
                                @foreach ($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', 'bank_wire') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('payment_method') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="entry-total">Total €</label>
                            <input type="number" id="entry-total" name="total" class="form-control" value="{{ old('total') }}" step="0.01" required>
                            @error('total') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="entry-fees">Fees €</label>
                            {{-- Typed as a positive figure: the column reads it as held back. --}}
                            <input type="number" id="entry-fees" name="fees" class="form-control" value="{{ old('fees') }}" step="0.01" min="0" placeholder="0.00">
                            @error('fees') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group entry-form-wide">
                            <label for="entry-remark">Remark</label>
                            <input type="text" id="entry-remark" name="remark" class="form-control" value="{{ old('remark') }}" maxlength="255">
                            @error('remark') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary" id="entry-submit">Add entry</button>
                    </div>
                </form>
            </dialog>

            {{-- Deleting is not undoable, so it asks first and names the line. --}}
            <dialog id="entry-delete-modal" class="modal" aria-labelledby="entry-delete-title">
                <form method="POST" id="entry-delete-form">
                    @csrf
                    @method('DELETE')
                    <h3 class="modal-title" id="entry-delete-title">Delete this entry?</h3>
                    <p class="modal-body">Once gone, <span id="entry-delete-label"></span> is not recoverable.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-accounting-entry.js') }}" defer></script>
@endpush
