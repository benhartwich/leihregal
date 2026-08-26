<?php

namespace App\Notifications;

use App\Mail\LoanDueSoonMail;
use App\Models\Loan;

class LoanDueSoonNotification extends BaseNotification
{
    public function __construct(public Loan $loan, public int $verbleibendeTage) {}

    public function toMail(object $notifiable): LoanDueSoonMail
    {
        return new LoanDueSoonMail($this->loan, $this->verbleibendeTage);
    }

    public function toDatabase(object $notifiable): array
    {
        $text = match ($this->verbleibendeTage) {
            0       => 'ist heute zur Rückgabe fällig.',
            1       => 'ist morgen zur Rückgabe fällig.',
            default => "ist in {$this->verbleibendeTage} Tagen zur Rückgabe fällig.",
        };

        return [
            'titel'  => $this->verbleibendeTage === 0 ? 'Heute fällig' : 'Rückgabe steht an',
            'text'   => "„{$this->loan->media->title}" . '" ' . $text,
            'url'    => route('loans.index'),
            'symbol' => 'warnung',
        ];
    }
}
