<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client's place in a training session.
 */
#[Fillable(['training_session_id', 'client_id', 'client_package_id', 'status', 'booked_at', 'cancelled_at', 'cancelled_by', 'charged_at', 'charge_waived', 'reminder_sent_at'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'booked_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'charged_at' => 'immutable_datetime',
            'charge_waived' => 'boolean',
            'reminder_sent_at' => 'immutable_datetime',
        ];
    }

    public function isCharged(): bool
    {
        return $this->charged_at !== null;
    }

    /**
     * Charged but not covered by any package: a session the client owes.
     */
    public function isOwed(): bool
    {
        return $this->isCharged() && $this->client_package_id === null;
    }

    /**
     * @return BelongsTo<ClientPackage, $this>
     */
    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    /**
     * @return BelongsTo<TrainingSession, $this>
     */
    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return HasMany<BookingStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(BookingStatusChange::class);
    }
}
