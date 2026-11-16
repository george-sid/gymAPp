<?php

namespace App\Actions\Calendar;

use App\Exceptions\BookingException;
use App\Models\CalendarEvent;
use App\Models\TrainingSession;
use Carbon\CarbonInterface;

/**
 * Throws when the trainer already has a session or calendar event in [from, to).
 */
class EnsureTrainerIsFree
{
    public function handle(int $trainerId, CarbonInterface $from, CarbonInterface $to, ?int $ignoreSessionId = null): void
    {
        $hasSession = TrainingSession::query()
            ->where('trainer_id', $trainerId)
            ->notCancelled()
            ->overlapping($from, $to)
            ->when($ignoreSessionId, fn ($query) => $query->whereKeyNot($ignoreSessionId))
            ->exists();

        $hasEvent = CalendarEvent::query()
            ->where('trainer_id', $trainerId)
            ->overlapping($from, $to)
            ->exists();

        if ($hasSession || $hasEvent) {
            throw BookingException::trainerBusy();
        }
    }
}
