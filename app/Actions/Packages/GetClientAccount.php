<?php

namespace App\Actions\Packages;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClientPackage;
use App\Models\Payment;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;

/**
 * A client's package balance: sessions left, sessions already booked ahead,
 * sessions owed and money owed.
 */
class GetClientAccount
{
    /**
     * @return array{available_sessions: int, upcoming_bookings: int, free_sessions: int, owed_sessions: int, amount_due: float}
     */
    public function handle(User $client): array
    {
        $availableSessions = ClientPackage::query()
            ->where('client_id', $client->id)
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', LocalTime::now()->toDateString()))
            ->withUsage()
            ->get()
            ->sum(fn (ClientPackage $package) => $package->remainingSessions());

        $upcomingBookings = Booking::query()
            ->where('client_id', $client->id)
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereHas('trainingSession', fn (Builder $query) => $query->notCancelled()->where('starts_at', '>', now()))
            ->count();

        $owedSessions = Booking::query()
            ->where('client_id', $client->id)
            ->whereNotNull('charged_at')
            ->whereNull('client_package_id')
            ->count();

        $packagesTotal = (float) ClientPackage::query()->where('client_id', $client->id)->sum('price');
        $paymentsTotal = (float) Payment::query()->where('client_id', $client->id)->sum('amount');

        return [
            'available_sessions' => (int) $availableSessions,
            'upcoming_bookings' => $upcomingBookings,
            'free_sessions' => (int) $availableSessions - $upcomingBookings,
            'owed_sessions' => $owedSessions,
            'amount_due' => round($packagesTotal - $paymentsTotal, 2),
        ];
    }

    /**
     * A warning for the trainer when the client's packages do not cover their booked sessions, or null.
     */
    public function bookingWarning(User $client): ?string
    {
        $account = $this->handle($client);

        if ($account['free_sessions'] >= 0 && $account['owed_sessions'] === 0) {
            return null;
        }

        return "Προσοχή: ο/η {$client->name} δεν έχει αρκετές συνεδρίες σε πακέτο "
            ."(διαθέσιμες {$account['available_sessions']}, κλεισμένα ραντεβού {$account['upcoming_bookings']}"
            .($account['owed_sessions'] > 0 ? ", χρωστάει {$account['owed_sessions']}" : '').').';
    }
}
