<?php

namespace App\Actions\Packages;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClientPackage;
use App\Support\LocalTime;
use App\Support\Settings;

/**
 * Charges or refunds one package session for a booking, based on its status:
 * completed, no-show and late cancellation use a session; anything else does not.
 * Uses the client's package that expires first; with no usable package the
 * session is charged without one and counts as owed. Call inside a transaction.
 */
class ApplyBookingCharge
{
    public function __construct(private Settings $settings) {}

    public function handle(Booking $booking): Booking
    {
        $booking->loadMissing('trainingSession');

        $shouldCharge = ! $booking->charge_waived && $this->shouldCharge($booking);

        if ($shouldCharge && ! $booking->isCharged()) {
            $booking->forceFill([
                'charged_at' => now(),
                'client_package_id' => $this->packageFor($booking)?->id,
            ])->save();
        } elseif (! $shouldCharge && $booking->isCharged()) {
            $booking->forceFill(['charged_at' => null, 'client_package_id' => null])->save();
        }

        return $booking;
    }

    public function isLateCancellation(Booking $booking): bool
    {
        $session = $booking->trainingSession;

        if ($booking->status !== BookingStatus::Cancelled || $booking->cancelled_at === null || $session->isCancelled()) {
            return false;
        }

        $freeUntil = $session->starts_at->subHours($this->settings->integer('free_cancellation_hours'));

        return $booking->cancelled_at->greaterThan($freeUntil);
    }

    private function shouldCharge(Booking $booking): bool
    {
        return match ($booking->status) {
            BookingStatus::Completed, BookingStatus::NoShow => true,
            BookingStatus::Cancelled => $this->isLateCancellation($booking),
            default => false,
        };
    }

    /**
     * The client's valid package for this session with sessions left, expiring first.
     */
    private function packageFor(Booking $booking): ?ClientPackage
    {
        $session = $booking->trainingSession;
        $sessionDate = LocalTime::fromUtc($session->starts_at)->toDateString();

        return ClientPackage::query()
            ->where('client_id', $booking->client_id)
            ->where(fn ($query) => $query->whereNull('service_id')->orWhere('service_id', $session->service_id))
            ->whereDate('starts_on', '<=', $sessionDate)
            ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', $sessionDate))
            ->withCount(['chargedBookings as sessions_used'])
            ->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->first(fn (ClientPackage $package) => $package->sessions_used < $package->sessions_total);
    }
}
