<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\TrainingSessionStatus;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A time slot on the trainer's calendar (UTC). Holds one booking (personal) or several (group).
 */
#[Fillable(['trainer_id', 'service_id', 'starts_at', 'ends_at', 'capacity', 'status', 'notes', 'recurrence_id'])]
class TrainingSession extends Model
{
    /** @use HasFactory<TrainingSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'status' => TrainingSessionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function activeBookings(): HasMany
    {
        return $this->bookings()->whereIn('status', BookingStatus::placeOccupyingValues());
    }

    public function isCancelled(): bool
    {
        return $this->status === TrainingSessionStatus::Cancelled;
    }

    public function localStartsAt(): CarbonImmutable
    {
        return LocalTime::fromUtc($this->starts_at);
    }

    public function localEndsAt(): CarbonImmutable
    {
        return LocalTime::fromUtc($this->ends_at);
    }

    public function durationInMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * Sessions that are not cancelled.
     *
     * @param  Builder<TrainingSession>  $query
     */
    #[Scope]
    protected function notCancelled(Builder $query): void
    {
        $query->where('status', '!=', TrainingSessionStatus::Cancelled);
    }

    /**
     * Sessions that overlap the [from, to) interval.
     *
     * @param  Builder<TrainingSession>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }
}
