<?php

namespace App\Enums;

enum CalendarEventType: string
{
    /** A personal appointment, e.g. doctor or errand. */
    case Personal = 'personal';

    /** Time blocked from bookings, e.g. admin work or a break. */
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Προσωπικό',
            self::Blocked => 'Μπλοκάρισμα',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Personal => '#64748b',
            self::Blocked => '#a1a1aa',
        };
    }
}
