<?php

namespace App\Enums;

enum MediaStatus: string
{
    case Verfuegbar    = 'verfuegbar';
    case Ausgeliehen   = 'ausgeliehen';
    case Reserviert    = 'reserviert';
    case InAufbereitung = 'in_aufbereitung';
    case Verloren      = 'verloren';
    case Ausgemustert  = 'ausgemustert';

    public function label(): string
    {
        return match($this) {
            self::Verfuegbar     => 'Verfügbar',
            self::Ausgeliehen    => 'Ausgeliehen',
            self::Reserviert     => 'Reserviert',
            self::InAufbereitung => 'In Aufbereitung',
            self::Verloren       => 'Verloren',
            self::Ausgemustert   => 'Ausgemustert',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Verfuegbar     => 'bg-green-50 text-green-700',
            self::Ausgeliehen    => 'bg-orange-50 text-orange-700',
            self::Reserviert     => 'bg-yellow-50 text-yellow-700',
            self::InAufbereitung => 'bg-blue-50 text-blue-700',
            self::Verloren       => 'bg-red-50 text-red-700',
            self::Ausgemustert   => 'bg-gray-100 text-gray-500',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::Verfuegbar;
    }
}
