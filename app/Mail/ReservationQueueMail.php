<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationQueueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public int $position,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Warteliste aktualisiert – ' . $this->reservation->media->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reservation-queue');
    }
}
