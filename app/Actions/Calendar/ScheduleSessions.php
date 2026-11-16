<?php

namespace App\Actions\Calendar;

use App\Exceptions\BookingException;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates one session, or a weekly series ("every Tuesday 18:00 for 8 weeks"),
 * optionally booking a client into each. Weeks that conflict are skipped.
 */
class ScheduleSessions
{
    public function __construct(
        private CreateTrainingSession $createTrainingSession,
        private CreateBooking $createBooking,
    ) {}

    /**
     * @param  string  $localStart  local wall-clock start, e.g. "2026-11-24 18:00"
     * @return array{created: array<int, TrainingSession>, skipped: array<int, string>}
     */
    public function handle(
        User $trainer,
        Service $service,
        string $localStart,
        int $weeks,
        User $actor,
        ?User $client = null,
        ?int $capacity = null,
        ?string $notes = null,
    ): array {
        $weeks = max(1, $weeks);
        $recurrenceId = $weeks > 1 ? (string) Str::uuid() : null;
        $firstStart = CarbonImmutable::parse($localStart, LocalTime::zone());
        $created = [];
        $skipped = [];

        for ($week = 0; $week < $weeks; $week++) {
            // Add weeks in local time so the wall-clock hour survives daylight-saving changes.
            $localOccurrence = $firstStart->addWeeks($week);

            try {
                $created[] = DB::transaction(function () use ($trainer, $service, $localOccurrence, $capacity, $notes, $recurrenceId, $client, $actor): TrainingSession {
                    $session = $this->createTrainingSession->handle($trainer, $service, $localOccurrence->utc(), $capacity, $notes, $recurrenceId);

                    if ($client) {
                        $this->createBooking->handle($session, $client, $actor);
                    }

                    return $session;
                });
            } catch (BookingException $exception) {
                if ($weeks === 1) {
                    throw $exception;
                }

                $skipped[] = $localOccurrence->format('d/m/Y H:i').': '.$exception->getMessage();
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
