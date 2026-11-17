<?php

namespace App\Models;

use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Database\Factories\ClientPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A package a client bought. Each charged booking uses one of its sessions.
 *
 * @property-read int|null $sessions_used
 * @property-read string|null $paid_total
 */
#[Fillable(['client_id', 'package_id', 'service_id', 'name', 'sessions_total', 'starts_on', 'expires_on', 'price', 'notes'])]
class ClientPackage extends Model
{
    /** @use HasFactory<ClientPackageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sessions_total' => 'integer',
            'starts_on' => 'date',
            'expires_on' => 'date',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * Bookings that used a session of this package.
     *
     * @return HasMany<Booking, $this>
     */
    public function chargedBookings(): HasMany
    {
        return $this->hasMany(Booking::class)->whereNotNull('charged_at');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Adds `sessions_used` and `paid_total` to each package.
     *
     * @param  Builder<ClientPackage>  $query
     */
    #[Scope]
    protected function withUsage(Builder $query): void
    {
        $query->withCount(['chargedBookings as sessions_used'])->withSum('payments as paid_total', 'amount');
    }

    public function sessionsUsed(): int
    {
        return $this->sessions_used ?? $this->chargedBookings()->count();
    }

    public function remainingSessions(): int
    {
        return max(0, $this->sessions_total - $this->sessionsUsed());
    }

    public function amountDue(): float
    {
        $paid = $this->paid_total ?? $this->payments()->sum('amount');

        return round(max(0, (float) $this->price - (float) $paid), 2);
    }

    public function isExpired(?CarbonInterface $on = null): bool
    {
        return $this->expires_on !== null && $this->expires_on->toDateString() < ($on ?? LocalTime::now())->toDateString();
    }

    public function isActive(?CarbonInterface $on = null): bool
    {
        return ! $this->isExpired($on) && $this->remainingSessions() > 0;
    }
}
