<?php

namespace App\Actions\Calendar;

use App\Enums\TrainingSessionStatus;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Puts a session for a service on the trainer's calendar.
 */
class CreateTrainingSession
{
    public function __construct(private EnsureTrainerIsFree $ensureTrainerIsFree) {}

    /**
     * @param  CarbonImmutable  $startsAt  UTC
     */
    public function handle(
        User $trainer,
        Service $service,
        CarbonImmutable $startsAt,
        ?int $capacity = null,
        ?string $notes = null,
        ?string $recurrenceId = null,
        bool $allowOverlap = false,
    ): TrainingSession {
        $startsAt = $startsAt->utc()->startOfMinute();
        $endsAt = $startsAt->addMinutes($service->duration_min);

        if (! $allowOverlap) {
            $this->ensureTrainerIsFree->handle($trainer->id, $startsAt, $endsAt);
        }

        return TrainingSession::query()->create([
            'trainer_id' => $trainer->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity ?? $service->capacity,
            'status' => TrainingSessionStatus::Scheduled,
            'notes' => $notes,
            'recurrence_id' => $recurrenceId,
        ]);
    }
}
