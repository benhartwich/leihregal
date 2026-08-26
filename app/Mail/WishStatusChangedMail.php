<?php

namespace App\Mail;

use App\Models\Wish;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WishStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Wish $wish) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihr Medienwunsch wurde bearbeitet',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.wish-status-changed');
    }
}
