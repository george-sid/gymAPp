<?php

namespace Database\Factories;

use App\Models\Measurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->client(),
            'measured_on' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'weight' => fake()->randomFloat(1, 55, 110),
            'body_fat' => fake()->randomFloat(1, 12, 35),
            'waist' => fake()->randomFloat(1, 65, 110),
            'chest' => null,
            'hips' => null,
            'arm' => null,
            'thigh' => null,
            'notes' => null,
        ];
    }
}
