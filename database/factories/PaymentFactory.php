<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'client_package_id' => null,
            'amount' => 100,
            'method' => PaymentMethod::Cash,
            'paid_on' => now()->toDateString(),
            'receipt_number' => null,
            'notes' => null,
            'recorded_by' => null,
        ];
    }
}
