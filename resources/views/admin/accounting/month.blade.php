{{--
    One month of the accounts.

    The two sections diverge from the first line — different columns, different
    totals, a different form — so each owns its page whole and this file only
    says which one to render.
--}}
@extends('layouts.admin')

@section('title', $title.' — '.\App\Support\AccountingPeriods::label($period))

@section('content')
    @php
        $monthKey = \App\Support\AccountingPeriods::key($period);
    @endphp

    @include('admin.accounting.partials.'.$section)
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/admin-accounting-entry.js') }}" defer></script>
@endpush
