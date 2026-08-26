<?php

namespace App\Notifications;

use App\Mail\NewMediaMail;
use App\Models\Media;
use App\Models\User;

class NewMediaNotification extends BaseNotification
{
    public function __construct(public Media $media, public User $recipient) {}

    public function toMail(object $notifiable): NewMediaMail
    {
        return new NewMediaMail($this->media, $this->recipient);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titel'  => 'Neues Medium',
            'text'   => "„{$this->media->title}" . '" passt zu einem Ihrer abonnierten Themen.',
            'url'    => route('media.show', $this->media->id),
            'symbol' => 'neu',
        ];
    }
}
