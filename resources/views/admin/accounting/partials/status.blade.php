{{-- Where the month stands. A closed month can be ruled off and printed; one
     still running cannot, on either side of the accounts. --}}
@if (\App\Support\AccountingPeriods::isClosed($period))
    <span class="admin-list-chip admin-list-chip--shipped">Closed</span>
@else
    <span class="admin-list-chip">In progress</span>
@endif
