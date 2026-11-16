<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_id' => User::factory()->admin(),
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ];
    }
}
