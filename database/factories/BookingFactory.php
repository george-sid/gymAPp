<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_session_id' => TrainingSession::factory(),
            'client_id' => User::factory()->client(),
            'status' => BookingStatus::Confirmed,
            'booked_at' => now(),
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'cancelled_at' => $status === BookingStatus::Cancelled ? now() : null,
        ]);
    }
}
