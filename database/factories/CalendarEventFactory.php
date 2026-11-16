<?php

namespace Database\Factories;

use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = LocalTime::toUtc(now()->addDays(2)->format('Y-m-d').' 12:00');

        return [
            'trainer_id' => User::factory()->admin(),
            'title' => 'Γιατρός',
            'type' => CalendarEventType::Personal,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'notes' => null,
        ];
    }

    public function at(string $localDateTime, int $minutes = 60): static
    {
        $startsAt = LocalTime::toUtc($localDateTime);

        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($minutes),
        ]);
    }
}
