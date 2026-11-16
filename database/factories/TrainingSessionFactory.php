<?php

namespace Database\Factories;

use App\Enums\TrainingSessionStatus;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = LocalTime::toUtc(now()->addDays(2)->format('Y-m-d').' 10:00');

        return [
            'trainer_id' => User::factory()->admin(),
            'service_id' => Service::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'capacity' => 1,
            'status' => TrainingSessionStatus::Scheduled,
            'notes' => null,
            'recurrence_id' => null,
        ];
    }

    /**
     * Starts at the given local time ("2026-11-20 18:00") and lasts the given minutes.
     */
    public function at(string $localDateTime, int $minutes = 60): static
    {
        $startsAt = LocalTime::toUtc($localDateTime);

        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($minutes),
        ]);
    }

    public function group(int $capacity = 6): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingSessionStatus::Cancelled,
        ]);
    }
}
