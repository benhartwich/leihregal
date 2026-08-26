<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Wartend   = 'wartend';
    case Bereit    = 'bereit';
    case Abgeholt  = 'abgeholt';
    case Storniert = 'storniert';

    public function label(): string
    {
        return match($this) {
            self::Wartend   => 'Wartend',
            self::Bereit    => 'Bereit zur Abholung',
            self::Abgeholt  => 'Abgeholt',
            self::Storniert => 'Storniert',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Wartend   => 'bg-yellow-50 text-yellow-700',
            self::Bereit    => 'bg-green-50 text-green-700',
            self::Abgeholt  => 'bg-gray-100 text-gray-500',
            self::Storniert => 'bg-red-50 text-red-600',
        };
    }
}
