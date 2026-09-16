@extends('layouts.admin')

@section('title', 'Log a shooting session')

@php
    $lines = old('lines', [['weapon_id' => '', 'ammunition_id' => '', 'distance' => '', 'quantity' => 1]]);
@endphp

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.sessions.index') }}">Sessions</a></p>
                    <h2 class="fftir-title">Log a shooting session</h2>
                </div>
                <a href="{{ route('admin.fftir.sessions.index') }}" class="btn btn-secondary">Back to sessions</a>
            </div>
        </header>

        @error('lines') <p class="form-error">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('admin.fftir.sessions.store') }}" class="fftir-form">
            @csrf

            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                @error('date') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="note">Note</label>
                <input type="text" id="note" name="note" class="form-control" value="{{ old('note') }}" maxlength="1000" placeholder="e.g. Zeroing the new scope">
                @error('note') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <h3 class="fftir-panel-title">Lines</h3>

            <div id="session-lines">
                @foreach ($lines as $index => $line)
                    <div class="fftir-line">
                        <span class="fftir-line-index" aria-hidden="true">Line</span>
                        <button type="button" class="fftir-line-remove" data-remove-line aria-label="Remove this line">&times;</button>
                        <div class="fftir-line-fields">
                            <div class="form-group">
                                <label for="weapon-{{ $index }}">Weapon</label>
                                <select id="weapon-{{ $index }}" name="lines[{{ $index }}][weapon_id]" class="form-control" data-line-weapon required>
                                    <option value="">Select a weapon</option>
                                    @foreach ($weapons as $weapon)
                                        <option
                                            value="{{ $weapon->id }}"
                                            data-caliber="{{ $weapon->caliber->value }}"
                                            @selected((string) ($line['weapon_id'] ?? '') === (string) $weapon->id)
                                        >
                                            {{ $weapon->brand }} {{ $weapon->model }} ({{ $weapon->type->label() }}, {{ $weapon->caliber->value }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('lines.'.$index.'.weapon_id') <p class="form-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="form-group">
                                <label for="ammunition-{{ $index }}">Ammunition</label>
                                <select id="ammunition-{{ $index }}" name="lines[{{ $index }}][ammunition_id]" class="form-control" data-line-ammunition>
                                    <option value="">Don't know / any</option>
                                    @foreach ($ammunitions as $ammunition)
                                        <option
                                            value="{{ $ammunition->id }}"
                                            data-caliber="{{ $ammunition->caliber->value }}"
                                            @selected((string) ($line['ammunition_id'] ?? '') === (string) $ammunition->id)
                                        >
                                            {{ $ammunition->brand }} {{ $ammunition->denomination }} ({{ number_format($ammunition->quantity) }} in stock)
                                        </option>
                                    @endforeach
                                </select>
                                @error('lines.'.$index.'.ammunition_id') <p class="form-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="form-group">
                                <label for="distance-{{ $index }}">Distance</label>
                                <select id="distance-{{ $index }}" name="lines[{{ $index }}][distance]" class="form-control" required>
                                    <option value="">Select a distance</option>
                                    @foreach ($distances as $distance)
                                        <option value="{{ $distance->value }}" @selected(($line['distance'] ?? '') === $distance->value)>{{ $distance->value }}</option>
                                    @endforeach
                                </select>
                                @error('lines.'.$index.'.distance') <p class="form-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="form-group">
                                <label for="quantity-{{ $index }}">Ammo used</label>
                                <input type="number" id="quantity-{{ $index }}" name="lines[{{ $index }}][quantity]" class="form-control" value="{{ $line['quantity'] ?? 1 }}" min="1" required>
                                @error('lines.'.$index.'.quantity') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <template id="session-line-row-template">
                <div class="fftir-line">
                    <span class="fftir-line-index" aria-hidden="true">Line</span>
                    <button type="button" class="fftir-line-remove" data-remove-line aria-label="Remove this line">&times;</button>
                    <div class="fftir-line-fields">
                        <div class="form-group">
                            <label for="weapon-__INDEX__">Weapon</label>
                            <select id="weapon-__INDEX__" name="lines[__INDEX__][weapon_id]" class="form-control" data-line-weapon required>
                                <option value="">Select a weapon</option>
                                @foreach ($weapons as $weapon)
                                    <option value="{{ $weapon->id }}" data-caliber="{{ $weapon->caliber->value }}">
                                        {{ $weapon->brand }} {{ $weapon->model }} ({{ $weapon->type->label() }}, {{ $weapon->caliber->value }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ammunition-__INDEX__">Ammunition</label>
                            <select id="ammunition-__INDEX__" name="lines[__INDEX__][ammunition_id]" class="form-control" data-line-ammunition>
                                <option value="">Don't know / any</option>
                                @foreach ($ammunitions as $ammunition)
                                    <option value="{{ $ammunition->id }}" data-caliber="{{ $ammunition->caliber->value }}">
                                        {{ $ammunition->brand }} {{ $ammunition->denomination }} ({{ number_format($ammunition->quantity) }} in stock)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="distance-__INDEX__">Distance</label>
                            <select id="distance-__INDEX__" name="lines[__INDEX__][distance]" class="form-control" required>
                                <option value="">Select a distance</option>
                                @foreach ($distances as $distance)
                                    <option value="{{ $distance->value }}">{{ $distance->value }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quantity-__INDEX__">Ammo used</label>
                            <input type="number" id="quantity-__INDEX__" name="lines[__INDEX__][quantity]" class="form-control" value="1" min="1" required>
                        </div>
                    </div>
                </div>
            </template>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="session-add-line">Add a line</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Log session</button>
                <a href="{{ route('admin.fftir.sessions.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush

@push('scripts')
    <script src="{{ versioned_asset('js/admin-fftir-session-lines.js') }}"></script>
@endpush
