@extends('layouts.admin')

@section('title', 'Shipping settings')

@section('content')
    @php
        $thresholdValue = old(
            'free_shipping_threshold',
            $setting->free_shipping_threshold_cents !== null
                ? number_format($setting->free_shipping_threshold_cents / 100, 2, '.', '')
                : ''
        );
        $selectedCarrierIds = old('free_shipping_carrier_ids', $setting->free_shipping_carrier_ids ?? []);
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Shipping</h2>
                    <p class="admin-list-lede">Set an order amount above which shipping becomes free, and pick which carriers it applies to.</p>
                </div>
            </div>
        </header>

        <form
            method="POST"
            action="{{ route('admin.settings.shipping.update') }}"
            class="admin-form-card admin-form-card--solo"
        >
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="free_shipping_threshold">Free shipping above (EUR)</label>
                <input
                    type="number"
                    id="free_shipping_threshold"
                    name="free_shipping_threshold"
                    class="form-control"
                    value="{{ $thresholdValue }}"
                    min="0"
                    max="99999.99"
                    step="0.01"
                    placeholder="e.g. 50.00"
                >
                <p class="form-hint">Leave blank to disable free shipping entirely.</p>
                @error('free_shipping_threshold') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label>Eligible carriers</label>
                <p class="form-hint">Shipping is free on these carriers once the order subtotal reaches the amount above. Other carriers keep their normal price.</p>

                <div class="admin-check-list">
                    @foreach ($carriers as $carrier)
                        <label class="form-check">
                            <input
                                type="checkbox"
                                name="free_shipping_carrier_ids[]"
                                value="{{ $carrier->id }}"
                                @checked(in_array($carrier->id, $selectedCarrierIds))
                            >
                            {{ $carrier->localizedName() }}
                            <span class="admin-check-list-meta">— {{ $carrier->formattedPrice() }}, {{ $carrier->method->value }}</span>
                        </label>
                    @endforeach
                </div>
                @error('free_shipping_carrier_ids') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection
