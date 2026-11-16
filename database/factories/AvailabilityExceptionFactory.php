<?php

namespace Database\Factories;

use App\Enums\AvailabilityExceptionType;
use App\Models\AvailabilityException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityException>
 */
class AvailabilityExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d');

        return [
            'trainer_id' => User::factory()->admin(),
            'type' => AvailabilityExceptionType::Closed,
            'starts_on' => $date,
            'ends_on' => $date,
            'start_time' => null,
            'end_time' => null,
            'reason' => 'Αργία',
        ];
    }

    public function extra(string $startTime = '18:00', string $endTime = '20:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AvailabilityExceptionType::Extra,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => null,
        ]);
    }
}
