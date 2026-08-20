<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a customer that the shop has answered them.
 *
 * Deliberately does not carry the reply text: it says an answer is waiting and
 * links to it. That keeps thread content out of inboxes and mail logs, and out
 * of any forwarded copy of the email.
 */
class ConversationReplied extends Notification
{
    public function __construct(private readonly Conversation $conversation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // A guest thread has no account behind it. Nothing about it should ever
        // reach an address we never authenticated, whatever the caller thinks.
        if ($this->conversation->isGuest()) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('store.conversation_notification_subject', ['subject' => $this->conversation->subject]))
            ->greeting(__('store.conversation_notification_greeting', ['name' => $this->conversation->name]))
            ->line(__('store.conversation_notification_line'))
            ->action(
                __('store.conversation_notification_action'),
                localized_route('account.conversations.show', ['conversation' => $this->conversation]),
            )
            ->salutation(__('store.conversation_notification_salutation', ['shop' => config('app.name')]));
    }
}
