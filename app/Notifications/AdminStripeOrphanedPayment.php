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
            ->subject('Stripe payment without an order — action needed')
            ->line('A checkout session was **paid** but its order could not be created:')
            ->line('Session `'.$this->sessionId.'` — '.$this->error)
            ->action('Open the orphaned payments', route('admin.stripe.orphaned-payments.index'))
            ->line('The session can be finalized into its order from that page (owner account required).');
    }
}
