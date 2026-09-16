@extends('layouts.admin')

@section('title', 'Weapons')

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.index') }}">FFTIR</a></p>
                    <h2 class="fftir-title">Weapons</h2>
                    <p class="fftir-lede">The armory register: every weapon, its caliber, and its cost per round fired.</p>
                </div>
                <div class="fftir-actions">
                    <a href="{{ route('admin.fftir.index') }}" class="btn btn-secondary">Back to FFTIR</a>
                    <a href="{{ route('admin.fftir.weapons.create') }}" class="btn btn-primary">Add weapon</a>
                </div>
            </div>
        </header>

        @if ($weapons->isEmpty())
            <div class="fftir-empty">
                <p>No weapons registered yet.</p>
                <a href="{{ route('admin.fftir.weapons.create') }}" class="btn btn-primary">Add weapon</a>
            </div>
        @else
            <div class="fftir-table-wrap">
                <table class="fftir-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Caliber</th>
                            <th>Price</th>
                            <th>Rounds fired</th>
                            <th>Price per round fired</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($weapons as $weapon)
                            <tr>
                                <td>{{ $weapon->type->label() }}</td>
                                <td>
                                    <a href="{{ route('admin.fftir.weapons.edit', $weapon) }}" class="fftir-strong">
                                        {{ $weapon->brand }}
                                    </a>
                                </td>
                                <td>{{ $weapon->model }}</td>
                                <td class="fftir-num">{{ $weapon->caliber->value }}</td>
                                <td class="fftir-num">{{ $weapon->price_cents !== null ? format_euros($weapon->price_cents) : 'N/A' }}</td>
                                <td class="fftir-num">{{ number_format($weapon->roundsFired()) }}</td>
                                @php($costPerRound = $weapon->costPerRoundFiredCents())
                                <td class="fftir-num">{{ $costPerRound !== null ? format_euros($costPerRound) : 'N/A' }}</td>
                                <td>
                                    <div class="fftir-row-actions">
                                        <a href="{{ route('admin.fftir.weapons.edit', $weapon) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush
