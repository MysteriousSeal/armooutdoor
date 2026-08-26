{{--
    The form for a hand-written entry, adding and correcting alike.

    Sales pick a kind from a short list; purchases write theirs out, since a
    purchase is whatever its invoice is for. Purchases also carry the VAT rate
    their invoice charged.
--}}
@php($isPurchases = $section === 'purchases')

<dialog id="entry-modal" class="modal modal--wide" aria-labelledby="entry-modal-title">
    <form method="POST" id="entry-form" action="{{ route('admin.accounting.entries.store', ['section' => $section, 'month' => $monthKey]) }}">
        @csrf
        {{-- Swapped for PUT when editing; one form serves both. --}}
        <input type="hidden" name="_method" id="entry-method" value="POST">

        <h3 class="modal-title" id="entry-modal-title">Add an entry</h3>
        <p class="modal-body">
            {{ $isPurchases
                ? 'A supplier invoice: goods for stock, supplies, a service bought in.'
                : 'Anything sold outside a shop order: a prestation, a repair, a sale made by hand.' }}
        </p>

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
                <label for="entry-client">{{ $isPurchases ? 'Supplier' : 'Client' }}</label>
                <input type="text" id="entry-client" name="client" class="form-control" value="{{ old('client') }}" maxlength="120">
                @error('client') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            @unless ($isPurchases)
                <div class="form-group">
                    <label for="entry-channel">Channel</label>
                    <input type="text" id="entry-channel" name="channel" class="form-control" value="{{ old('channel') }}" maxlength="80" placeholder="Direct">
                    @error('channel') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endunless

            <div class="form-group">
                <label for="entry-type">Type</label>
                @if ($isPurchases)
                    {{-- Free text: a purchase is whatever its invoice is for. --}}
                    <input
                        type="text"
                        id="entry-type"
                        name="type"
                        class="form-control"
                        value="{{ old('type') }}"
                        maxlength="120"
                        placeholder="achat stock, achat fournitures…"
                        required
                    >
                @else
                    <select id="entry-type" name="type" class="form-control" required>
                        @foreach ($entryTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
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
                <label for="entry-total">{{ $isPurchases ? 'Total incl. VAT €' : 'Total €' }}</label>
                <input type="number" id="entry-total" name="total" class="form-control" value="{{ old('total') }}" step="0.01" required>
                @error('total') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            @if ($isPurchases)
                <div class="form-group">
                    {{-- The rate as the invoice charged it. The amount before tax
                         and the tax itself are worked back from the total. --}}
                    <label for="entry-vat">VAT rate %</label>
                    <input type="number" id="entry-vat" name="vat_rate" class="form-control" value="{{ old('vat_rate', '20') }}" step="0.1" min="0" max="100" required>
                    @error('vat_rate') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @else
                <div class="form-group">
                    {{-- Typed as a positive figure: the column reads it as held back. --}}
                    <label for="entry-fees">Fees €</label>
                    <input type="number" id="entry-fees" name="fees" class="form-control" value="{{ old('fees') }}" step="0.01" min="0" placeholder="0.00">
                    @error('fees') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif

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
