<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Quartalsbericht mit PDF im Anhang (Phase 8).
 */
class QuarterlyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $bericht,
        public string $pdfInhalt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quartalsbericht ' . $this->bericht['bezeichnung'] . ' – ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.quarterly-report');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfInhalt, 'leihregal-quartalsbericht-' . str_replace(' ', '-', $this->bericht['bezeichnung']) . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
