<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The confirmation a guest gets right after writing: their message arrived,
 * and here is the private link where the exchange will happen. Sending the
 * link to the address they gave is also what proves they own it.
 */
class GuestConversationStarted extends Notification
{
    public function __construct(private readonly Conversation $conversation) {}

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
            ->subject(__('store.guest_started_subject', ['subject' => $this->conversation->subject]))
            ->greeting(__('store.conversation_notification_greeting', ['name' => $this->conversation->name]))
            ->line(__('store.guest_started_line'))
            ->action(__('store.guest_started_action'), $this->conversation->guestUrl())
            ->line(__('store.guest_started_keep'))
            ->salutation(__('store.conversation_notification_salutation', ['shop' => config('app.name')]));
    }
}
