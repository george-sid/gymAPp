<?php

namespace App\Actions\Packages;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

/**
 * Lets the trainer forgive (or re-apply) the session charge of a booking,
 * e.g. a late cancellation for a good reason.
 */
class WaiveBookingCharge
{
    public function __construct(private ApplyBookingCharge $applyBookingCharge) {}

    public function handle(Booking $booking, bool $waive = true): Booking
    {
        return DB::transaction(function () use ($booking, $waive): Booking {
            $booking->forceFill(['charge_waived' => $waive])->save();

            return $this->applyBookingCharge->handle($booking);
        });
    }
}
