<x-mail::message>
# New order {{ $order->number }}

**{{ $channel }}** — {{ trim(($order->address_snapshot['first_name'] ?? '').' '.($order->address_snapshot['last_name'] ?? '')) }}@if ($order->user?->email && ! $order->user->external) ({{ $order->user->email }})@endif

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
