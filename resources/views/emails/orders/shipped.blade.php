<x-mail::message>
# Votre commande est expédiée

Bonjour {{ $order->address_snapshot['first_name'] ?? '' }},

@if ($order->trackingCarrierName() !== '')
Bonne nouvelle : votre commande **{{ $order->number }}** vient d'être
expédiée avec {{ $order->trackingCarrierName() }}.
@else
Bonne nouvelle : votre commande **{{ $order->number }}** vient d'être
expédiée.
@endif

@if ($order->hasTracking())
Numéro de suivi : **{{ $order->tracking_number }}**

@if ($order->trackingUrl())
[Suivre mon colis]({{ $order->trackingUrl() }})

@endif
@endif
<x-mail::button :url="localized_route('orders.show', ['order' => $order->number])">
Suivre ma commande
</x-mail::button>

À bientôt,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
