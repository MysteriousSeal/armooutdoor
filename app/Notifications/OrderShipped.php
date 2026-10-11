<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The order left the shop: the promise the preparation email makes, kept.
 * Carries the tracking when there is one, and points at the order page.
 */
class OrderShipped extends Notification
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
            ->subject(__('store.order_shipped_subject', ['number' => $this->order->number]))
            ->markdown('emails.orders.shipped', ['order' => $this->order]);
    }
}
