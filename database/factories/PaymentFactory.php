<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => 'ord_'.fake()->unique()->numerify('######'),
            'customer_id' => 'cus_'.fake()->unique()->numerify('######'),
            'amount' => fake()->numberBetween(100, 100_000),
            'currency' => 'USD',
            'status' => PaymentStatus::DEFAULT,
            'card_last_four' => fake()->numerify('####'),
        ];
    }

    public function authorized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::AUTHORIZED,
            'bank_authorization_id' => 'auth_'.fake()->uuid(),
            'authorized_at' => now(),
        ]);
    }

    public function captured(): static
    {
        return $this->authorized()->state(fn (array $attributes) => [
            'status' => PaymentStatus::CAPTURED,
            'bank_capture_id' => 'cap_'.fake()->uuid(),
            'captured_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->authorized()->state(fn (array $attributes) => [
            'status' => PaymentStatus::VOIDED,
            'bank_void_id' => 'void_'.fake()->uuid(),
            'voided_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->captured()->state(fn (array $attributes) => [
            'status' => PaymentStatus::REFUNDED,
            'bank_refund_id' => 'ref_'.fake()->uuid(),
            'refunded_at' => now(),
        ]);
    }
}
