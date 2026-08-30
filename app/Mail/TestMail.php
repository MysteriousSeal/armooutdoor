<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email the admin sends to itself (or anywhere) to prove the pipes work.
 * Branded like the shop so the test also answers "what do our emails look
 * like in an inbox".
 */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Email de test — Armo Outdoor',
        );
    }

    public function content(): Content
    {
        // Rendered through the shop's shared mail theme on purpose: the test
        // email exists to show what every other email will look like.
        return new Content(
            markdown: 'emails.test',
            with: [
                'sentAt' => now(),
                'mailer' => (string) config('mail.default'),
            ],
        );
    }
}
