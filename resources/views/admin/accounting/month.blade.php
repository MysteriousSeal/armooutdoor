@extends('layouts.admin')

@section('title', $title.' — '.\App\Support\AccountingPeriods::label($period))

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">
                <a href="{{ route('admin.accounting.'.$section) }}">{{ $title }}</a>
            </p>
            <h2 class="admin-list-title">{{ \App\Support\AccountingPeriods::label($period) }}</h2>
            <p class="admin-list-lede">
                {{ $period->locale('en')->isoFormat('D MMMM') }} to {{ $period->endOfMonth()->locale('en')->isoFormat('D MMMM YYYY') }}
            </p>
        </header>

        <p class="empty-state">Nothing here yet.</p>
    </div>
@endsection
