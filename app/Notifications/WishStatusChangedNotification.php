<?php

namespace App\Notifications;

use App\Mail\WishStatusChangedMail;
use App\Models\Wish;

class WishStatusChangedNotification extends BaseNotification
{
    public function __construct(public Wish $wish) {}

    public function toMail(object $notifiable): WishStatusChangedMail
    {
        return new WishStatusChangedMail($this->wish);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titel'  => 'Wunsch bearbeitet',
            'text'   => 'Ihr Wunsch'
                        . ($this->wish->title ? " „{$this->wish->title}" . '"' : '')
                        . ' hat den Status „' . $this->wish->status->value . '".',
            'url'    => route('wishes.index'),
            'symbol' => 'wunsch',
        ];
    }
}
