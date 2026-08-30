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
        // A guest thread only speaks once it holds a private link to point
        // at — never to an address with nothing safe to link to.
        if ($this->conversation->isGuest() && $this->conversation->guest_token === null) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isGuest = $this->conversation->isGuest();

        return (new MailMessage)
            ->subject(__('store.conversation_notification_subject', ['subject' => $this->conversation->subject]))
            ->greeting(__('store.conversation_notification_greeting', ['name' => $this->conversation->name]))
            ->line($isGuest
                ? __('store.conversation_notification_line_guest')
                : __('store.conversation_notification_line'))
            ->action(
                __('store.conversation_notification_action'),
                $isGuest
                    ? $this->conversation->guestUrl()
                    : localized_route('account.conversations.show', ['conversation' => $this->conversation]),
            )
            ->salutation(__('store.conversation_notification_salutation', ['shop' => config('app.name')]));
    }
}
