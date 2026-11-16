<?php

namespace App\Actions\Calendar;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reschedules a session (drag & drop or edit), checking trainer and client conflicts.
 */
class MoveTrainingSession
{
    public function __construct(private EnsureTrainerIsFree $ensureTrainerIsFree) {}

    /**
     * @param  CarbonImmutable  $startsAt  UTC
     * @param  CarbonImmutable|null  $endsAt  UTC; keeps the current duration when null
     */
    public function handle(TrainingSession $session, CarbonImmutable $startsAt, ?CarbonImmutable $endsAt = null): TrainingSession
    {
        if ($session->isCancelled()) {
            throw BookingException::sessionCancelled();
        }

        $startsAt = $startsAt->utc()->startOfMinute();
        $endsAt = ($endsAt ?? $startsAt->addMinutes($session->durationInMinutes()))->utc()->startOfMinute();

        if ($endsAt <= $startsAt) {
            throw new BookingException('Η λήξη πρέπει να είναι μετά την έναρξη.');
        }

        return DB::transaction(function () use ($session, $startsAt, $endsAt): TrainingSession {
            $session = TrainingSession::query()->lockForUpdate()->findOrFail($session->id);

            $this->ensureTrainerIsFree->handle($session->trainer_id, $startsAt, $endsAt, $session->id);
            $this->ensureClientsAreFree($session, $startsAt, $endsAt);

            $session->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);

            return $session;
        });
    }

    private function ensureClientsAreFree(TrainingSession $session, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $busyBooking = Booking::query()
            ->whereIn('client_id', $session->activeBookings()->select('client_id'))
            ->whereIn('status', BookingStatus::placeOccupyingValues())
            ->whereHas('trainingSession', fn ($query) => $query
                ->notCancelled()
                ->whereKeyNot($session->id)
                ->overlapping($startsAt, $endsAt))
            ->with('client')
            ->first();

        if ($busyBooking) {
            throw BookingException::clientBusy($busyBooking->client->name);
        }
    }
}
