<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Σε αναμονή',
            self::Confirmed => 'Επιβεβαιωμένη',
            self::Completed => 'Ολοκληρώθηκε',
            self::NoShow => 'No-show',
            self::Cancelled => 'Ακυρώθηκε',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Confirmed => 'sky',
            self::Completed => 'emerald',
            self::NoShow => 'red',
            self::Cancelled => 'zinc',
        };
    }

    /**
     * Whether the booking takes up a place in its session.
     */
    public function occupiesPlace(): bool
    {
        return $this !== self::Cancelled;
    }

    /**
     * @return array<int, string>
     */
    public static function placeOccupyingValues(): array
    {
        return array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->occupiesPlace()),
        );
    }
}
