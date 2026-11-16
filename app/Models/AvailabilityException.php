<?php

namespace App\Models;

use App\Enums\AvailabilityExceptionType;
use Database\Factories\AvailabilityExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A holiday, leave or one-off change to the weekly hours (local dates and times).
 */
#[Fillable(['trainer_id', 'type', 'starts_on', 'ends_on', 'start_time', 'end_time', 'reason'])]
class AvailabilityException extends Model
{
    /** @use HasFactory<AvailabilityExceptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AvailabilityExceptionType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function isAllDay(): bool
    {
        return $this->start_time === null || $this->end_time === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Exceptions that cover the given local date (Y-m-d).
     *
     * @param  Builder<AvailabilityException>  $query
     */
    #[Scope]
    protected function coveringDate(Builder $query, string $date): void
    {
        $query->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }
}
