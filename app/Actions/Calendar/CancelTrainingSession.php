<?php

namespace App\Actions\Calendar;

use App\Enums\BookingStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a session and every booking in it.
 */
class CancelTrainingSession
{
    public function __construct(private ChangeBookingStatus $changeBookingStatus) {}

    public function handle(TrainingSession $session, User $actor): TrainingSession
    {
        return DB::transaction(function () use ($session, $actor): TrainingSession {
            // Cancel the session first so its bookings are never charged as late cancellations.
            $session->update(['status' => TrainingSessionStatus::Cancelled]);

            foreach ($session->activeBookings()->get() as $booking) {
                $this->changeBookingStatus->handle($booking, BookingStatus::Cancelled, $actor);
            }

            return $session;
        });
    }
}
