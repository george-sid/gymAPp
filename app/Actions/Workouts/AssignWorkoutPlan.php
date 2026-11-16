<?php

namespace App\Actions\Workouts;

use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Support\Facades\DB;

/**
 * Gives a client their own copy of a plan (days and exercises included) and
 * makes it their active plan, so it can be adjusted without touching the template.
 */
class AssignWorkoutPlan
{
    public function handle(WorkoutPlan $source, User $client, User $trainer): WorkoutPlan
    {
        return DB::transaction(function () use ($source, $client, $trainer): WorkoutPlan {
            WorkoutPlan::query()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $copy = WorkoutPlan::query()->create([
                'trainer_id' => $trainer->id,
                'client_id' => $client->id,
                'source_plan_id' => $source->id,
                'name' => $source->name,
                'description' => $source->description,
                'is_active' => true,
                'assigned_at' => now(),
            ]);

            foreach ($source->days()->with('exercises')->get() as $day) {
                $dayCopy = $copy->days()->create($day->only(['name', 'position']));

                foreach ($day->exercises as $exercise) {
                    $dayCopy->exercises()->create($exercise->only(['exercise_id', 'sets', 'reps', 'weight', 'rest_sec', 'notes', 'position']));
                }
            }

            return $copy;
        });
    }
}
