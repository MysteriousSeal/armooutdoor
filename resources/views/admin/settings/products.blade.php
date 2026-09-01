@extends('layouts.admin')

@section('title', 'Product settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Products</h2>
                    <p class="admin-list-lede">
                        How the catalogue presents its stock to customers.
                    </p>
                </div>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.settings.products.update') }}" class="admin-form-card admin-form-card--solo">
            @csrf
            @method('PUT')

            <h3 class="admin-panel-title">Stock display</h3>

            <div class="form-group">
                <label for="low_stock_threshold">Low-stock threshold</label>
                <input
                    type="number"
                    id="low_stock_threshold"
                    name="low_stock_threshold"
                    class="form-control"
                    value="{{ old('low_stock_threshold', $setting->low_stock_threshold) }}"
                    min="1"
                    max="999"
                    required
                >
                @error('low_stock_threshold') <p class="form-error">{{ $message }}</p> @enderror
                <p class="form-hint">
                    At or below this quantity, a product (or a size) shows « Derniers stocks disponibles »
                    instead of « En stock ». The dashboard's low-stock alerts follow the same number.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection
