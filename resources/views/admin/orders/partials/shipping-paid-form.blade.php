{{-- What the parcel cost to send, which is not always what the customer was
     charged for it. Shown once per page: with the marketplace costs on a
     marketplace order, with the shipping details on any other. --}}
<form method="POST" action="{{ route('admin.orders.shipping-paid.update', $order) }}" class="order-shipping-form">
    @csrf
    @method('PATCH')
    <div class="order-shipping-field">
        <label for="shipping_paid">Shipping paid (EUR)</label>
        <input
            type="number"
            id="shipping_paid"
            name="shipping_paid"
            class="order-shipping-input"
            value="{{ old('shipping_paid', $order->shipping_paid_cents !== null ? number_format($order->shipping_paid_cents / 100, 2, '.', '') : '') }}"
            min="0"
            max="99999.99"
            step="0.01"
            placeholder="e.g. 5.00"
        >
        @error('shipping_paid') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <button type="submit" class="btn btn-secondary btn-block">Save shipping paid</button>
</form>
