<?php

namespace App\Notifications;

use App\Mail\DateReservationAvailableMail;
use App\Models\Reservation;

class DateReservationAvailableNotification extends BaseNotification
{
    public function __construct(public Reservation $reservation) {}

    public function toMail(object $notifiable): DateReservationAvailableMail
    {
        return new DateReservationAvailableMail($this->reservation);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titel'  => 'Terminreservierung verfügbar',
            'text'   => "„{$this->reservation->media->title}" . '" ist für Ihren gebuchten Zeitraum wieder da.',
            'url'    => route('media.show', $this->reservation->media_id),
            'symbol' => 'bereit',
        ];
    }
}
