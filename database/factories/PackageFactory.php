<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '10 συνεδρίες',
            'service_id' => null,
            'sessions_count' => 10,
            'valid_days' => 60,
            'price' => 300,
            'is_active' => true,
        ];
    }
}
