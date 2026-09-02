<x-mail::message>
# Bienvenue, {{ $user->first_name }}.

Votre compte est prêt. Il garde vos adresses pour la fois suivante, retrouve votre panier d'une visite à l'autre, et suit vos commandes de la préparation à la livraison.

Une question sur un article ou une commande ? Écrivez-nous depuis [le formulaire de contact]({{ localized_route('contact.show') }}) : on vous répond au plus vite.

<x-mail::button :url="localized_route('home')">
Découvrir la boutique
</x-mail::button>

À bientôt,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
