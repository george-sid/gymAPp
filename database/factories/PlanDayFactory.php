<?php

namespace Database\Factories;

use App\Models\PlanDay;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanDay>
 */
class PlanDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_plan_id' => WorkoutPlan::factory(),
            'name' => 'Ημέρα '.fake()->numberBetween(1, 7),
            'position' => 0,
        ];
    }
}
