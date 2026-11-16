<?php

namespace App\Enums;

enum AvailabilityExceptionType: string
{
    /** Not available (holiday, leave, closed hours). */
    case Closed = 'closed';

    /** Available outside the usual weekly hours. */
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Κλειστό',
            self::Extra => 'Επιπλέον ώρες',
        };
    }
}
