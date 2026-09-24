{{-- How many photos the product has on our shop: its main photo plus its
     gallery. None in red, one in amber, two or more plain. --}}
@if ($count === null)
    <span class="nb-none">—</span>
@elseif ($count === 0)
    <span class="admin-availability-chip is-out-of-stock" title="No photo on our shop">0</span>
@elseif ($count === 1)
    <span class="admin-availability-chip is-low-stock" title="Only one photo on our shop">1</span>
@else
    {{ $count }}
@endif
