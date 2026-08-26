<?php

namespace App\Notifications;

use App\Mail\WishFulfilledMail;
use App\Models\Media;
use App\Models\Wish;

class WishFulfilledNotification extends BaseNotification
{
    public function __construct(public Wish $wish, public Media $media) {}

    public function toMail(object $notifiable): WishFulfilledMail
    {
        return new WishFulfilledMail($this->wish, $this->media);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titel'  => 'Wunsch erfüllt',
            'text'   => "„{$this->media->title}" . '" ist jetzt im Bestand.',
            'url'    => route('media.show', $this->media->id),
            'symbol' => 'wunsch',
        ];
    }
}
