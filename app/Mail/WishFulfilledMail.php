<?php

namespace App\Mail;

use App\Models\Media;
use App\Models\Wish;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WishFulfilledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Wish $wish, public Media $media) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Wunsch ist da: ' . $this->media->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.wish-fulfilled');
    }
}
