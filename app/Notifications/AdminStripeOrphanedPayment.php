<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The worst silence in the system: Stripe took the money and the order
 * could not be created. Somebody paid and nothing will ship until a human
 * finalizes the session by hand.
 */
class AdminStripeOrphanedPayment extends Notification
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $error,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Paiement Stripe sans commande — action requise')
            ->line("Une session de paiement a été **payée** mais sa commande n'a pas pu être créée :")
            ->line('Session `'.$this->sessionId.'` — '.$this->error)
            ->action('Ouvrir les paiements orphelins', route('admin.stripe.orphaned-payments.index'))
            ->line('La session peut être finalisée en commande depuis cette page (compte propriétaire requis).');
    }
}
