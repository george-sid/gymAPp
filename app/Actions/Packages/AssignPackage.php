<?php

namespace App\Actions\Packages;

use App\Models\Booking;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sells a package to a client. Sessions the client already owes (charged
 * without a package) are covered by the new package first, oldest first.
 */
class AssignPackage
{
    public function handle(User $client, Package $package, string $startsOn, ?string $price = null, ?string $notes = null): ClientPackage
    {
        return DB::transaction(function () use ($client, $package, $startsOn, $price, $notes): ClientPackage {
            $start = CarbonImmutable::parse($startsOn);

            $clientPackage = ClientPackage::query()->create([
                'client_id' => $client->id,
                'package_id' => $package->id,
                'service_id' => $package->service_id,
                'name' => $package->name,
                'sessions_total' => $package->sessions_count,
                'starts_on' => $start->toDateString(),
                'expires_on' => $package->valid_days ? $start->addDays($package->valid_days)->toDateString() : null,
                'price' => $price ?? $package->price,
                'notes' => $notes,
            ]);

            $owedBookingIds = Booking::query()
                ->where('bookings.client_id', $client->id)
                ->whereNotNull('bookings.charged_at')
                ->whereNull('bookings.client_package_id')
                ->where('bookings.charge_waived', false)
                ->when($package->service_id, fn (Builder $query) => $query->whereHas(
                    'trainingSession',
                    fn (Builder $session) => $session->where('service_id', $package->service_id),
                ))
                ->join('training_sessions', 'training_sessions.id', '=', 'bookings.training_session_id')
                ->orderBy('training_sessions.starts_at')
                ->limit($package->sessions_count)
                ->lockForUpdate()
                ->pluck('bookings.id');

            Booking::query()->whereKey($owedBookingIds)->update(['client_package_id' => $clientPackage->id]);

            return $clientPackage;
        });
    }
}
