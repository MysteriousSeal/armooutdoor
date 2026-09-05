<?php

namespace App\Notifications;

use App\Models\BlogComment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the shop a comment just appeared under an article. Comments
 * publish themselves, so this is the moderation queue: the shop reads
 * its inbox and prunes from the article page when needed.
 */
class AdminBlogCommentReceived extends Notification
{
    public function __construct(private readonly BlogComment $comment) {}

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
            ->subject('Nouveau commentaire — '.$this->comment->post->localizedTitle())
            ->line('**'.$this->comment->authorLabel().'** vient de commenter « '.$this->comment->post->localizedTitle().' » :')
            ->line('« '.str($this->comment->body)->limit(300).' »')
            ->action("Voir l'article", route('blog.show', $this->comment->post->slug).'#commentaires');
    }
}
