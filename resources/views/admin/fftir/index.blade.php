@extends('layouts.admin')

@section('title', 'FFTIR')

@section('content')
    <div class="fftir-page fftir-index">
        <header class="fftir-hero">
            <p class="fftir-eyebrow">Fédération Française de Tir</p>
            <h2 class="fftir-title">FFTIR</h2>
            <p class="fftir-lede">The armory register, the ammunition ledger, and the range log.</p>
        </header>

        <div class="fftir-tile-grid">
            <a href="{{ route('admin.fftir.weapons.index') }}" class="fftir-tile">
                @include('admin.fftir.partials.corner-frame')
                <span class="fftir-tile-arrow" aria-hidden="true">&rarr;</span>
                <span class="fftir-tile-code">WPN</span>
                <span class="fftir-tile-label">Weapons</span>
                <span class="fftir-tile-desc">Type, caliber, price, rounds fired</span>
            </a>
            <a href="{{ route('admin.fftir.ammunitions.index') }}" class="fftir-tile">
                @include('admin.fftir.partials.corner-frame')
                <span class="fftir-tile-arrow" aria-hidden="true">&rarr;</span>
                <span class="fftir-tile-code">AMO</span>
                <span class="fftir-tile-label">Ammunitions</span>
                <span class="fftir-tile-desc">Stock on hand and cost per round</span>
            </a>
            <a href="{{ route('admin.fftir.sessions.index') }}" class="fftir-tile">
                @include('admin.fftir.partials.corner-frame')
                <span class="fftir-tile-arrow" aria-hidden="true">&rarr;</span>
                <span class="fftir-tile-code">LOG</span>
                <span class="fftir-tile-label">Sessions</span>
                <span class="fftir-tile-desc">Range visits and ammo consumed</span>
            </a>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush
