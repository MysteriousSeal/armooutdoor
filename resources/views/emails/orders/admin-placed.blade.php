<x-mail::message>
# New order {{ $order->number }}

**{{ $channel }}** — {{ trim(($order->address_snapshot['first_name'] ?? '').' '.($order->address_snapshot['last_name'] ?? '')) }}@if ($order->user?->email && ! $order->user->external) ({{ $order->user->email }})@endif

@if ($ageProof)
@php
    $identity = $order->user?->identityStatus();
    $ageLine = match ($ageProof['state']) {
        'verified' => $identity['until']
            ? 'Age verified, covered until '.$identity['until']->format('d/m/Y').'. This order may be dispatched.'
            : 'Age verified. This order may be dispatched.',
        'pending' => 'A proof of age is waiting to be reviewed. Do not dispatch until it is.',
        'expired' => 'The proof of age on file expired on '.$identity['at']?->format('d/m/Y').'. A new one is needed before dispatch.',
        'rejected' => 'The proof of age on file was rejected. A new one is needed before dispatch.',
        default => 'No proof of age on file for this customer. Ask for one before dispatch.',
    };
@endphp
<x-mail::panel>
**Reserved to adults** — {{ $ageProof['items']->map->localizedName()->join(', ') }}

{{ $ageLine }} *Status as at the time of sending.*
</x-mail::panel>
@endif

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->localizedName() }}@if ($item->variant_label) — {{ $item->variant_label }}@endif | {{ $item->quantity }} | {{ format_euros($item->line_cents) }} |
@endforeach
| **Subtotal** | | {{ format_euros($order->subtotal_cents) }} |
@if ($order->discount_cents > 0)
| Discount | | −{{ format_euros($order->discount_cents) }} |
@endif
| Shipping{{ $order->shipping_discount_cents > 0 ? ' (discounted)' : '' }} | | {{ format_euros(max(0, $order->shipping_cents - $order->shipping_discount_cents)) }} |
| **Total** | | **{{ format_euros($order->total_cents) }}** |
</x-mail::table>

**Delivery** — {{ $order->carrierName() }}
@if ($order->relay_snapshot)
Relay point: {{ $order->relay_snapshot['name'] ?? '' }}, {{ $order->relay_snapshot['city'] ?? '' }}
@else
{{ $order->address_snapshot['line1'] ?? '' }}@if (! empty($order->address_snapshot['line2'])), {{ $order->address_snapshot['line2'] }}@endif<br>
{{ $order->address_snapshot['postal_code'] ?? '' }} {{ $order->address_snapshot['city'] ?? '' }}
@endif

**Payment** — {{ $order->payment_method->label() }}

<x-mail::button :url="route('admin.orders.show', $order)">
Open in the admin
</x-mail::button>
</x-mail::message>
