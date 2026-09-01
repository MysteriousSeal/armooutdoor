<x-mail::message>
# Merci pour votre commande !

Bonjour {{ $order->address_snapshot['first_name'] ?? '' }},

Nous avons bien reçu votre commande **{{ $order->number }}**.

@if ($order->payment_method !== \App\Enums\PaymentMethod::Card)
<x-mail::panel>
**Paiement en attente :** votre commande sera préparée et expédiée dès confirmation de votre paiement.
</x-mail::panel>
@endif

@if ($ageProof)
@php
    $identity = $order->user?->identityStatus();
    $ageMessage = match ($ageProof['state']) {
        'pending' => __('store.email_age_pending'),
        'expired' => __('store.email_age_expired', ['date' => $identity['at']?->translatedFormat('d F Y')]),
        'rejected' => __('store.email_age_rejected'),
        'verified' => $identity['until']
            ? __('store.email_age_verified_until', ['date' => $identity['until']->translatedFormat('d F Y')])
            : __('store.email_age_verified'),
        default => __('store.email_age_none'),
    };
@endphp
<x-mail::panel>
**{{ __('store.email_age_title') }}** ({{ $ageProof['items']->map->localizedName()->join(', ') }})

{{ $ageMessage }} *{{ __('store.email_age_asof') }}*
@if (! in_array($ageProof['state'], ['verified', 'pending'], true))

[{{ __('store.email_age_cta') }}]({{ route('account.documents.index') }})
@endif
</x-mail::panel>
@endif

<x-mail::table>
| Article | Qté | Prix |
|:--------|:---:|-----:|
@foreach ($order->items as $item)
| {{ $item->localizedName() }}@if ($item->variant_label) · {{ $item->variant_label }}@endif | {{ $item->quantity }} | {{ format_euros($item->line_cents) }} |
@endforeach
| **Sous-total** | | {{ format_euros($order->subtotal_cents) }} |
@if ($order->discount_cents > 0)
| Remise | | −{{ format_euros($order->discount_cents) }} |
@endif
| Livraison{{ $order->shipping_discount_cents > 0 ? ' (remise déduite)' : '' }} | | {{ format_euros(max(0, $order->shipping_cents - $order->shipping_discount_cents)) }} |
| **Total** | | **{{ format_euros($order->total_cents) }}** |
</x-mail::table>

**Livraison** : {{ $order->carrierName() }}
@if ($order->relay_snapshot)
Point relais : {{ $order->relay_snapshot['name'] ?? '' }}, {{ $order->relay_snapshot['city'] ?? '' }}
@else
{{ trim(($order->address_snapshot['first_name'] ?? '').' '.($order->address_snapshot['last_name'] ?? '')) }}<br>
{{ $order->address_snapshot['line1'] ?? '' }}@if (! empty($order->address_snapshot['line2'])), {{ $order->address_snapshot['line2'] }}@endif<br>
{{ $order->address_snapshot['postal_code'] ?? '' }} {{ $order->address_snapshot['city'] ?? '' }}
@endif

<x-mail::button :url="localized_route('orders.show', ['order' => $order->number])">
Suivre ma commande
</x-mail::button>

À bientôt,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
