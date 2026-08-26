<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'media_id',
        'user_id',
        'position',
        'status',
        'notified_at',
        'reserved_from',
        'reserved_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'        => ReservationStatus::class,
            'notified_at'   => 'datetime',
            'reserved_from' => 'date',
            'reserved_until'=> 'date',
        ];
    }

    public function isDated(): bool
    {
        return $this->reserved_from !== null;
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [ReservationStatus::Wartend, ReservationStatus::Bereit]);
    }
}
