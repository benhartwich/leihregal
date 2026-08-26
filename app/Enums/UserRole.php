<?php

namespace App\Enums;

enum UserRole: string
{
    case Betreuer = 'betreuer';
    case Kurator  = 'kurator';
    case Admin    = 'admin';

    /** Human-readable German label */
    public function label(): string
    {
        return match($this) {
            self::Betreuer => 'Betreuer:in',
            self::Kurator  => 'Kurator:in',
            self::Admin    => 'Administrator:in',
        };
    }

    /** Tailwind badge color classes */
    public function badgeClass(): string
    {
        return match($this) {
            self::Betreuer => 'bg-blue-100 text-blue-800',
            self::Kurator  => 'bg-emerald-100 text-emerald-800',
            self::Admin    => 'bg-purple-100 text-purple-800',
        };
    }
}
