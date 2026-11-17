<?php

namespace Database\Factories;

use App\Models\ClientPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPackage>
 */
class ClientPackageFactory extends Factory
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
            'package_id' => null,
            'service_id' => null,
            'name' => '10 συνεδρίες',
            'sessions_total' => 10,
            'starts_on' => now()->subMonth()->toDateString(),
            'expires_on' => now()->addMonths(2)->toDateString(),
            'price' => 300,
            'notes' => null,
        ];
    }

    public function sessions(int $count): static
    {
        return $this->state(fn (array $attributes) => ['sessions_total' => $count]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => now()->subMonths(3)->toDateString(),
            'expires_on' => now()->subDay()->toDateString(),
        ]);
    }
}
