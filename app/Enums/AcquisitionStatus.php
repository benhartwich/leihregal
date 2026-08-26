<?php

namespace App\Enums;

enum AcquisitionStatus: string
{
    case Offen        = 'offen';
    case Bestellt     = 'bestellt';
    case Verworfen    = 'verworfen';
    case Eingetroffen = 'eingetroffen';

    public function label(): string
    {
        return match($this) {
            self::Offen        => 'Offen',
            self::Bestellt     => 'Bestellt',
            self::Verworfen    => 'Verworfen',
            self::Eingetroffen => 'Eingetroffen',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Offen        => 'bg-blue-50 text-blue-700',
            self::Bestellt     => 'bg-yellow-50 text-yellow-700',
            self::Verworfen    => 'bg-gray-100 text-gray-500',
            self::Eingetroffen => 'bg-green-50 text-green-700',
        };
    }
}
