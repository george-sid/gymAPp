<?php

namespace App\Models;

use Database\Factories\AvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One block of weekly working hours, e.g. Monday 07:00–14:00 (local time).
 */
#[Fillable(['trainer_id', 'weekday', 'start_time', 'end_time'])]
class AvailabilityRule extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
    use HasFactory;

    public const array WEEKDAYS = [
        1 => 'Δευτέρα',
        2 => 'Τρίτη',
        3 => 'Τετάρτη',
        4 => 'Πέμπτη',
        5 => 'Παρασκευή',
        6 => 'Σάββατο',
        7 => 'Κυριακή',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
