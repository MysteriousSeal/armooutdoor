@extends('layouts.admin')

@section('title', 'Edit line')

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.sessions.index') }}">Sessions</a></p>
                    <h2 class="fftir-title">Edit line</h2>
                    <p class="fftir-lede">Part of the session on {{ $session->date->format('d/m/Y') }}.</p>
                </div>
                <a href="{{ route('admin.fftir.sessions.index') }}" class="btn btn-secondary">Back to sessions</a>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.fftir.sessions.lines.update', [$session, $line]) }}" class="fftir-form">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="weapon_id">Weapon</label>
                <select id="weapon_id" name="weapon_id" class="form-control" data-line-weapon required>
                    <option value="">Select a weapon</option>
                    @foreach ($weapons as $weapon)
                        <option
                            value="{{ $weapon->id }}"
                            data-caliber="{{ $weapon->caliber->value }}"
                            @selected((string) old('weapon_id', $line->fftir_weapon_id) === (string) $weapon->id)
                        >
                            {{ $weapon->brand }} {{ $weapon->model }} ({{ $weapon->type->label() }}, {{ $weapon->caliber->value }})
                        </option>
                    @endforeach
                </select>
                @error('weapon_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="ammunition_id">Ammunition</label>
                <select id="ammunition_id" name="ammunition_id" class="form-control" data-line-ammunition>
                    <option value="">Don't know / any</option>
                    @foreach ($ammunitions as $ammunition)
                        <option
                            value="{{ $ammunition->id }}"
                            data-caliber="{{ $ammunition->caliber->value }}"
                            @selected((string) old('ammunition_id', $line->fftir_ammunition_id) === (string) $ammunition->id)
                        >
                            {{ $ammunition->brand }} {{ $ammunition->denomination }} ({{ number_format($ammunition->quantity) }} in stock)
                        </option>
                    @endforeach
                </select>
                @error('ammunition_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="distance">Distance</label>
                <select id="distance" name="distance" class="form-control" required>
                    <option value="">Select a distance</option>
                    @foreach ($distances as $distance)
                        <option value="{{ $distance->value }}" @selected(old('distance', $line->distance->value) === $distance->value)>{{ $distance->value }}</option>
                    @endforeach
                </select>
                @error('distance') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="quantity">Ammo used</label>
                <input type="number" id="quantity" name="quantity" class="form-control" value="{{ old('quantity', $line->quantity) }}" min="1" required>
                @error('quantity') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('admin.fftir.sessions.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush

@push('scripts')
    <script src="{{ versioned_asset('js/admin-fftir-line-edit.js') }}"></script>
@endpush
