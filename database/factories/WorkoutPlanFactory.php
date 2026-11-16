<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutPlan>
 */
class WorkoutPlanFactory extends Factory
{
    /**
     * A template (no client) by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_id' => User::factory()->admin(),
            'client_id' => null,
            'source_plan_id' => null,
            'name' => 'Full Body '.fake()->numberBetween(1, 999),
            'description' => null,
            'is_active' => true,
            'assigned_at' => null,
        ];
    }

    public function forClient(User $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
            'assigned_at' => now(),
        ]);
    }
}
