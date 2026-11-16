<?php

namespace App\Actions\Calendar;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingStatusChange;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Books a client into a session. The session row is locked so two bookings
 * cannot take the last place at the same time.
 */
class CreateBooking
{
    public function handle(TrainingSession $session, User $client, User $actor, BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        if ($client->role !== UserRole::Client) {
            throw BookingException::notAClient();
        }

        return DB::transaction(function () use ($session, $client, $actor, $status): Booking {
            $locked = TrainingSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($locked->isCancelled()) {
                throw BookingException::sessionCancelled();
            }

            $existing = Booking::query()
                ->where('training_session_id', $locked->id)
                ->where('client_id', $client->id)
                ->first();

            if ($existing?->status->occupiesPlace()) {
                throw BookingException::alreadyBooked();
            }

            if ($locked->activeBookings()->count() >= $locked->capacity) {
                throw BookingException::sessionFull();
            }

            $this->ensureClientIsFree($locked, $client);

            $booking = $existing ?? new Booking([
                'training_session_id' => $locked->id,
                'client_id' => $client->id,
            ]);
            $previousStatus = $existing?->status;

            $booking->fill([
                'status' => $status,
                'booked_at' => now(),
                'cancelled_at' => null,
                'cancelled_by' => null,
            ])->save();

            BookingStatusChange::query()->create([
                'booking_id' => $booking->id,
                'from_status' => $previousStatus,
                'to_status' => $status,
                'changed_by' => $actor->id,
            ]);

            return $booking;
        });
    }

    private function ensureClientIsFree(TrainingSession $session, User $client): void
    {
        $hasOverlap = Booking::query()
            ->where('client_id', $client->id)
            ->whereIn('status', BookingStatus::placeOccupyingValues())
            ->whereHas('trainingSession', fn ($query) => $query
                ->notCancelled()
                ->whereKeyNot($session->id)
                ->overlapping($session->starts_at, $session->ends_at))
            ->exists();

        if ($hasOverlap) {
            throw BookingException::clientBusy($client->name);
        }
    }
}
