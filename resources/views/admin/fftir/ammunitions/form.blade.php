@extends('layouts.admin')

@section('title', $ammunition->exists ? 'Edit ammunition' : 'Add ammunition')

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.ammunitions.index') }}">Ammunitions</a></p>
                    <h2 class="fftir-title">{{ $ammunition->exists ? 'Edit ammunition' : 'Add ammunition' }}</h2>
                </div>
                <a href="{{ route('admin.fftir.ammunitions.index') }}" class="btn btn-secondary">Back to ammunitions</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $ammunition->exists ? route('admin.fftir.ammunitions.update', $ammunition) : route('admin.fftir.ammunitions.store') }}"
            class="fftir-form"
        >
            @csrf
            @if ($ammunition->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" class="form-control" value="{{ old('brand', $ammunition->brand) }}" required maxlength="80" placeholder="e.g. Sellier &amp; Bellot">
                @error('brand') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="caliber">Caliber</label>
                <select id="caliber" name="caliber" class="form-control" required>
                    <option value="">Select a caliber</option>
                    @foreach (\App\Enums\Caliber::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('caliber', $ammunition->caliber?->value) === $case->value)>{{ $case->value }}</option>
                    @endforeach
                </select>
                @error('caliber') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="denomination">Denomination</label>
                <input type="text" id="denomination" name="denomination" class="form-control" value="{{ old('denomination', $ammunition->denomination) }}" required maxlength="80" placeholder="e.g. FMJ 124gr">
                @error('denomination') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $ammunition->exists ? 'Save changes' : 'Add ammunition' }}</button>
                <a href="{{ route('admin.fftir.ammunitions.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        @if ($ammunition->exists)
            <div class="fftir-panel">
                <div class="fftir-panel-row">
                    <h3 class="fftir-panel-title">Stock</h3>
                    <a href="{{ route('admin.fftir.ammunitions.stock-history', $ammunition) }}" class="btn btn-secondary btn-sm">View history</a>
                </div>
                <p class="fftir-chip fftir-num">{{ number_format($ammunition->quantity) }} in stock</p>
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush
