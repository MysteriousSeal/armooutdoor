<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient la boutique elle-même qu'une commande vient de devenir réelle —
 * passée au checkout ou saisie à la main. Le détail complet, pour lire la
 * commande depuis la boîte mail sans ouvrir l'admin.
 */
class AdminOrderPlaced extends Notification
{
    public function __construct(private readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function channel(): string
    {
        return match (true) {
            $this->order->marketplace !== null => $this->order->marketplace->name,
            (bool) $this->order->is_manual => 'Commande manuelle',
            default => 'Site',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle commande '.$this->order->number.' — '.format_euros($this->order->total_cents))
            ->markdown('emails.orders.admin-placed', [
                'order' => $this->order->loadMissing('items.product', 'user.identityDocuments', 'marketplace'),
                // The proof, resolved once here rather than by the template.
                'ageProof' => $this->order->ageProofSummary(),
                'channel' => $this->channel(),
            ]);
    }
}
