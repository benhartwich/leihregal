<?php

namespace App\Enums;

enum MediaType: string
{
    case Buch            = 'buch';
    case Gefuehlskarten  = 'gefuehlskarten';
    case Spiel           = 'spiel';
    case Zeitschrift     = 'zeitschrift';
    case Arbeitsmaterial = 'arbeitsmaterial';
    case Digital         = 'digital';

    public function label(): string
    {
        return match($this) {
            self::Buch            => 'Buch',
            self::Gefuehlskarten  => 'Gefühlskarten',
            self::Spiel           => 'Spiel',
            self::Zeitschrift     => 'Zeitschrift',
            self::Arbeitsmaterial => 'Arbeitsmaterial',
            self::Digital         => 'Digital',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Buch            => '📖',
            self::Gefuehlskarten  => '🃏',
            self::Spiel           => '🎲',
            self::Zeitschrift     => '📰',
            self::Arbeitsmaterial => '📋',
            self::Digital         => '💻',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Buch            => 'bg-blue-50 text-blue-700',
            self::Gefuehlskarten  => 'bg-purple-50 text-purple-700',
            self::Spiel           => 'bg-yellow-50 text-yellow-700',
            self::Zeitschrift     => 'bg-orange-50 text-orange-700',
            self::Arbeitsmaterial => 'bg-teal-50 text-teal-700',
            self::Digital         => 'bg-gray-50 text-gray-700',
        };
    }
}
