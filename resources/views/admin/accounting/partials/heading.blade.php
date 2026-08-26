{{-- The month's name and the days it covers. Shared by both sections. --}}
<div>
    <p class="admin-list-kicker">
        <a href="{{ route('admin.accounting.'.$section) }}">{{ $title }}</a>
    </p>
    {{-- The chip sits on the title's line: it qualifies the month, so it
         belongs beside its name rather than under the dates. --}}
    <div class="accounting-month-heading">
        <h2 class="admin-list-title">{{ \App\Support\AccountingPeriods::label($period) }}</h2>
        @include('admin.accounting.partials.status')
    </div>
    <p class="admin-list-lede">
        {{ $period->locale('en')->isoFormat('D MMMM') }} to {{ $period->endOfMonth()->locale('en')->isoFormat('D MMMM YYYY') }}
    </p>
</div>
