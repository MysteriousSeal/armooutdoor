<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The order moved from "received" to "someone is packing it": short, warm,
 * and pointing at the order page for everything else.
 */
class OrderPreparing extends Notification
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
            ->subject(__('store.order_preparing_subject', ['number' => $this->order->number]))
            ->markdown('emails.orders.preparing', ['order' => $this->order]);
    }
}
