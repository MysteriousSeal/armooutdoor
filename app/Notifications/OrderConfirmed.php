<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The receipt a customer expects the moment they order: what they bought,
 * what it cost, where it's going — and for a bank wire, that nothing moves
 * until the transfer lands.
 */
class OrderConfirmed extends Notification
{
    public function __construct(private readonly Order $order) {}

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
            ->subject(__('store.order_confirmed_subject', ['number' => $this->order->number]))
            ->markdown('emails.orders.confirmed', [
                'order' => $this->order->loadMissing('items.product', 'user.identityDocuments'),
                // The proof, resolved once here rather than by the template.
                'ageProof' => $this->order->ageProofSummary(),
            ]);
    }
}
