<?php

namespace App\Enums;

enum WishStatus: string
{
    case Eingereicht = 'eingereicht';
    case Angenommen  = 'angenommen';
    case Abgelehnt   = 'abgelehnt';
    case Beobachten  = 'beobachten';

    public function label(): string
    {
        return match($this) {
            self::Eingereicht => 'Eingereicht',
            self::Angenommen  => 'Angenommen',
            self::Abgelehnt   => 'Abgelehnt',
            self::Beobachten  => 'Wird beobachtet',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Eingereicht => 'bg-blue-50 text-blue-700',
            self::Angenommen  => 'bg-green-50 text-green-700',
            self::Abgelehnt   => 'bg-red-50 text-red-600',
            self::Beobachten  => 'bg-yellow-50 text-yellow-700',
        };
    }
}
