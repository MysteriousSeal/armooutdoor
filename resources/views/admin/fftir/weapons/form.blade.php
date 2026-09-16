@extends('layouts.admin')

@section('title', $weapon->exists ? 'Edit weapon' : 'Add weapon')

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.weapons.index') }}">Weapons</a></p>
                    <h2 class="fftir-title">{{ $weapon->exists ? 'Edit weapon' : 'Add weapon' }}</h2>
                </div>
                <a href="{{ route('admin.fftir.weapons.index') }}" class="btn btn-secondary">Back to weapons</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $weapon->exists ? route('admin.fftir.weapons.update', $weapon) : route('admin.fftir.weapons.store') }}"
            class="fftir-form"
        >
            @csrf
            @if ($weapon->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" class="form-control" required>
                    @foreach (\App\Enums\WeaponType::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('type', $weapon->type?->value) === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                @error('type') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" class="form-control" value="{{ old('brand', $weapon->brand) }}" required maxlength="80" placeholder="e.g. Glock">
                @error('brand') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="model">Model</label>
                <input type="text" id="model" name="model" class="form-control" value="{{ old('model', $weapon->model) }}" required maxlength="80" placeholder="e.g. 17 Gen5">
                @error('model') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="caliber">Caliber</label>
                <select id="caliber" name="caliber" class="form-control" required>
                    <option value="">Select a caliber</option>
                    @foreach (\App\Enums\Caliber::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('caliber', $weapon->caliber?->value) === $case->value)>{{ $case->value }}</option>
                    @endforeach
                </select>
                @error('caliber') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="price">Price €</label>
                <input
                    type="number"
                    id="price"
                    name="price"
                    class="form-control"
                    value="{{ old('price', $weapon->price_cents !== null ? number_format($weapon->price_cents / 100, 2, '.', '') : '') }}"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                >
                @error('price') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $weapon->exists ? 'Save changes' : 'Add weapon' }}</button>
                <a href="{{ route('admin.fftir.weapons.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush
