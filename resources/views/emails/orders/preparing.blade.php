<x-mail::message>
# Votre commande est en préparation

Bonjour {{ $order->address_snapshot['first_name'] ?? '' }},

Bonne nouvelle : votre commande **{{ $order->number }}** est en cours de
préparation. Nous vous préviendrons dès qu'elle sera expédiée.

<x-mail::button :url="localized_route('orders.show', ['order' => $order->number])">
Suivre ma commande
</x-mail::button>

À bientôt,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
