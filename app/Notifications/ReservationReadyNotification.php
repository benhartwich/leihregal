<?php

namespace App\Notifications;

use App\Mail\ReservationReadyMail;
use App\Models\Reservation;

class ReservationReadyNotification extends BaseNotification
{
    public function __construct(public Reservation $reservation) {}

    public function toMail(object $notifiable): ReservationReadyMail
    {
        return new ReservationReadyMail($this->reservation);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titel'  => 'Reservierung abholbereit',
            'text'   => "„{$this->reservation->media->title}" . '" liegt für Sie bereit.',
            'url'    => route('loans.index'),
            'symbol' => 'bereit',
        ];
    }
}
