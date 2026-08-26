<?php

namespace App\Notifications;

use App\Mail\ReservationQueueMail;
use App\Models\Reservation;

class ReservationQueueNotification extends BaseNotification
{
    public function __construct(public Reservation $reservation, public int $position) {}

    public function toMail(object $notifiable): ReservationQueueMail
    {
        return new ReservationQueueMail($this->reservation, $this->position);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titel'  => 'Warteliste aktualisiert',
            'text'   => "Sie stehen bei „{$this->reservation->media->title}" . '" an Position '
                        . $this->position . '.',
            'url'    => route('loans.index'),
            'symbol' => 'warteliste',
        ];
    }
}
