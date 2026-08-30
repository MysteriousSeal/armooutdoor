<x-mail::message>
# La messagerie fonctionne.

Cet email a été envoyé depuis la page de test du back office. S'il est arrivé
jusqu'ici — avec l'en-tête et les couleurs de la boutique — l'envoi d'emails
est opérationnel, et voici exactement à quoi ressemblent les emails d'Armo
Outdoor dans une boîte de réception.

<x-mail::panel>
**Envoyé le :** {{ $sentAt->format('d/m/Y à H:i:s') }}<br>
**Via le transport :** {{ $mailer }}
</x-mail::panel>

<x-mail::button :url="config('app.url')">
Voir la boutique
</x-mail::button>

Armo Outdoor — email automatique, aucune action attendue.
</x-mail::message>
