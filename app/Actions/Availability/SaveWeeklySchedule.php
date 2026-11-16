<?php

namespace App\Actions\Availability;

use App\Models\AvailabilityRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a trainer's weekly working hours.
 */
class SaveWeeklySchedule
{
    /**
     * @param  array<int, array<int, array{start: string, end: string}>>  $schedule  ranges per ISO weekday (local time)
     */
    public function handle(User $trainer, array $schedule): void
    {
        DB::transaction(function () use ($trainer, $schedule): void {
            AvailabilityRule::query()->where('trainer_id', $trainer->id)->delete();

            foreach ($schedule as $weekday => $ranges) {
                foreach ($ranges as $range) {
                    AvailabilityRule::query()->create([
                        'trainer_id' => $trainer->id,
                        'weekday' => $weekday,
                        'start_time' => $range['start'],
                        'end_time' => $range['end'],
                    ]);
                }
            }
        });
    }
}
