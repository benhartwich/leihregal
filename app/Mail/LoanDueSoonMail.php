<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Erinnerung VOR Ablauf der Leihfrist (Spec 4.6: 3 Tage, 1 Tag, am Fälligkeitstag).
 */
class LoanDueSoonMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Loan $loan, public int $verbleibendeTage) {}

    public function envelope(): Envelope
    {
        $betreff = match ($this->verbleibendeTage) {
            0       => 'Heute fällig: ' . $this->loan->media->title,
            1       => 'Morgen fällig: ' . $this->loan->media->title,
            default => "Rückgabe in {$this->verbleibendeTage} Tagen: " . $this->loan->media->title,
        };

        return new Envelope(subject: $betreff);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.loan-due-soon');
    }
}
