<?php

namespace App\Notifications;

use App\Mail\LoanOverdueMail;
use App\Models\Loan;

class LoanOverdueNotification extends BaseNotification
{
    public function __construct(public Loan $loan) {}

    public function toMail(object $notifiable): LoanOverdueMail
    {
        return new LoanOverdueMail($this->loan);
    }

    public function toDatabase(object $notifiable): array
    {
        $tage = (int) $this->loan->due_at->diffInDays(now());

        return [
            'titel'  => 'Rückgabe überfällig',
            'text'   => "„{$this->loan->media->title}" . '" war am '
                        . $this->loan->due_at->format('d.m.Y') . ' fällig'
                        . ($tage > 0 ? " – seit {$tage} Tag" . ($tage === 1 ? '' : 'en') : '') . '.',
            'url'    => route('loans.index'),
            'symbol' => 'warnung',
        ];
    }
}
