@extends('layouts.admin')

@section('title', 'Carrier settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Carriers</h2>
                    <p class="admin-list-lede">Shipping price charged per carrier, before any free-shipping rule applies.</p>
                </div>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.settings.carriers.update') }}" class="admin-form-card admin-form-card--solo">
            @csrf
            @method('PUT')

            <div class="admin-check-list admin-check-list--rows">
                @foreach ($carriers as $carrier)
                    <div class="admin-check-list-row">
                        <div>
                            <span class="admin-table-strong">{{ $carrier->localizedName() }}</span>
                            <span class="admin-check-list-meta">— {{ $carrier->method->value }}, {{ $carrier->localizedEta() }}</span>
                        </div>
                        <div class="form-group">
                            <label for="carrier-price-{{ $carrier->id }}" class="sr-only">Price for {{ $carrier->localizedName() }}</label>
                            <input
                                type="number"
                                id="carrier-price-{{ $carrier->id }}"
                                name="carriers[{{ $carrier->id }}][price]"
                                class="form-control"
                                value="{{ old('carriers.'.$carrier->id.'.price', number_format($carrier->price_cents / 100, 2, '.', '')) }}"
                                min="0"
                                max="9999.99"
                                step="0.01"
                                required
                            >
                            @error('carriers.'.$carrier->id.'.price') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection
