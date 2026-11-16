<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\PlanDay;
use App\Models\PlanExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanExercise>
 */
class PlanExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_day_id' => PlanDay::factory(),
            'exercise_id' => Exercise::factory(),
            'sets' => 3,
            'reps' => '10',
            'weight' => 20,
            'rest_sec' => 90,
            'notes' => null,
            'position' => 0,
        ];
    }
}
