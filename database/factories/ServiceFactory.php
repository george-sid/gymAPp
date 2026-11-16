<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Personal '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'duration_min' => 60,
            'price' => 35,
            'capacity' => 1,
            'color' => '#059669',
            'is_public' => true,
        ];
    }

    public function group(int $capacity = 6): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
            'color' => '#7c3aed',
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
