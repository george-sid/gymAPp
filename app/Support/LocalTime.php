<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Converts between UTC (storage) and the trainer's local time (display / input).
 */
final class LocalTime
{
    public static function zone(): string
    {
        return config('app.display_timezone');
    }

    /**
     * Parses a local wall-clock time ("2026-11-20 18:00") and returns it in UTC.
     */
    public static function toUtc(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, self::zone())->utc();
    }

    public static function fromUtc(CarbonInterface $utc): CarbonImmutable
    {
        return CarbonImmutable::instance($utc)->setTimezone(self::zone());
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    /**
     * The UTC instants that bound a local calendar day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayBoundsUtc(string $localDate): array
    {
        $start = CarbonImmutable::parse($localDate, self::zone())->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }
}
