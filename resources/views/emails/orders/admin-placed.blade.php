<x-mail::message>
# Nouvelle commande {{ $order->number }}

**{{ $channel }}** — {{ trim(($order->address_snapshot['first_name'] ?? '').' '.($order->address_snapshot['last_name'] ?? '')) }}@if ($order->user?->email && ! $order->user->external) ({{ $order->user->email }})@endif

@if ($ageProof)
@php
    $identity = $order->user?->identityStatus();
    $ageLine = match ($ageProof['state']) {
        'verified' => $identity['until']
            ? 'Majorité vérifiée, couverte jusqu\'au '.$identity['until']->format('d/m/Y').'. Cette commande peut partir.'
            : 'Majorité vérifiée. Cette commande peut partir.',
        'pending' => 'Une preuve de majorité attend d\'être vérifiée. Ne pas expédier avant.',
        'expired' => 'La preuve de majorité au dossier a expiré le '.$identity['at']?->format('d/m/Y').'. Il en faut une nouvelle avant l\'expédition.',
        'rejected' => 'La preuve de majorité au dossier a été refusée. Il en faut une nouvelle avant l\'expédition.',
        default => 'Aucune preuve de majorité au dossier pour ce client. En demander une avant l\'expédition.',
    };
@endphp
<x-mail::panel>
**Réservé aux majeurs** — {{ $ageProof['items']->map->localizedName()->join(', ') }}

{{ $ageLine }} *Statut à la date d'envoi.*
</x-mail::panel>
@endif

<x-mail::table>
| Article | Qté | Prix |
|:--------|:---:|-----:|
@foreach ($order->items as $item)
| {{ $item->localizedName() }}@if ($item->variant_label) — {{ $item->variant_label }}@endif | {{ $item->quantity }} | {{ format_euros($item->line_cents) }} |
@endforeach
| **Sous-total** | | {{ format_euros($order->subtotal_cents) }} |
@if ($order->discount_cents > 0)
| Remise | | −{{ format_euros($order->discount_cents) }} |
@endif
| Livraison{{ $order->shipping_discount_cents > 0 ? ' (remise déduite)' : '' }} | | {{ format_euros(max(0, $order->shipping_cents - $order->shipping_discount_cents)) }} |
| **Total** | | **{{ format_euros($order->total_cents) }}** |
</x-mail::table>

**Livraison** — {{ $order->carrierName() }}
@if ($order->relay_snapshot)
Point relais : {{ $order->relay_snapshot['name'] ?? '' }}, {{ $order->relay_snapshot['city'] ?? '' }}
@else
{{ $order->address_snapshot['line1'] ?? '' }}@if (! empty($order->address_snapshot['line2'])), {{ $order->address_snapshot['line2'] }}@endif<br>
{{ $order->address_snapshot['postal_code'] ?? '' }} {{ $order->address_snapshot['city'] ?? '' }}
@endif

**Paiement** — {{ $order->payment_method->label() }}

<x-mail::button :url="route('admin.orders.show', $order)">
Ouvrir dans l'admin
</x-mail::button>
</x-mail::message>
