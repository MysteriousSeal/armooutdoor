{{-- How many photos the product has on our shop: its main photo plus its
     gallery. Every product should have at least five: none or one in red,
     two to four in amber, five or more in green. --}}
@if ($count === null)
    <span class="nb-none">-</span>
@elseif ($count <= 1)
    <span class="admin-availability-chip is-out-of-stock" title="{{ $count === 0 ? 'No photo' : 'Only one photo' }} on our shop, five wanted">{{ $count }}</span>
@elseif ($count < 5)
    <span class="admin-availability-chip is-low-stock" title="{{ $count }} photos on our shop, five wanted">{{ $count }}</span>
@else
    <span class="admin-availability-chip is-in-stock" title="{{ $count }} photos on our shop">{{ $count }}</span>
@endif
