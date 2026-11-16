<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Trainer = 'trainer';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Διαχειριστής',
            self::Trainer => 'Trainer',
            self::Client => 'Πελάτης',
        };
    }

    /**
     * Staff roles are allowed to sign in to the admin panel.
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::Admin, self::Trainer], true);
    }
}
