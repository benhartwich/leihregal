<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanOverdueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Loan $loan) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Erinnerung: Rückgabe überfällig – ' . $this->loan->media->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.loan-overdue');
    }
}
