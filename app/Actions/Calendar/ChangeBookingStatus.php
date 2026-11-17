<?php

namespace App\Actions\Calendar;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingStatusChange;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a booking to another status (confirm, complete, no-show, cancel, restore)
 * and records who did it.
 */
class ChangeBookingStatus
{
    public function handle(Booking $booking, BookingStatus $to, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $to, $actor): Booking {
            $session = TrainingSession::query()->lockForUpdate()->findOrFail($booking->training_session_id);
            $booking->refresh();
            $from = $booking->status;

            if ($from === $to) {
                return $booking;
            }

            if (! $from->occupiesPlace() && $to->occupiesPlace()) {
                if ($session->isCancelled()) {
                    throw BookingException::sessionCancelled();
                }

                if ($session->activeBookings()->count() >= $session->capacity) {
                    throw BookingException::sessionFull();
                }
            }

            $booking->status = $to;

            if ($to === BookingStatus::Cancelled) {
                $booking->cancelled_at = now();
                $booking->cancelled_by = $actor->id;
            } elseif ($from === BookingStatus::Cancelled) {
                $booking->cancelled_at = null;
                $booking->cancelled_by = null;
            }

            $booking->save();

            BookingStatusChange::query()->create([
                'booking_id' => $booking->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor->id,
            ]);

            if ($to === BookingStatus::Cancelled) {
                BookingCancelled::dispatch($booking);
            }

            return $booking;
        });
    }
}
