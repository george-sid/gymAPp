<?php

namespace Database\Factories;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Άσκηση '.fake()->unique()->numberBetween(1, 99999),
            'muscle_group' => fake()->randomElement(MuscleGroup::cases()),
            'equipment' => fake()->randomElement(['Μπάρα', 'Αλτήρες', 'Μηχάνημα', null]),
            'video_url' => null,
            'image_path' => null,
            'instructions' => fake()->sentence(),
        ];
    }
}
