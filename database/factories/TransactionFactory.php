<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_amount' => $this->faker->numberBetween(1000, 50000), // Amount in cents (10.00 to 500.00)
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD']),
            'status' => $this->faker->randomElement([
                \App\Enums\TransactionStatusEnum::PENDING_PAYMENT->value,
                \App\Enums\TransactionStatusEnum::CONFIRMED->value,
                \App\Enums\TransactionStatusEnum::FAILED_PAYMENT->value,
                \App\Enums\TransactionStatusEnum::REFUNDED->value
            ]),
            'payment_gateway' => $this->faker->randomElement(['stripe', 'paypal']),
            'payment_gateway_transaction_id' => $this->faker->optional(0.8)->regexify('[A-Z0-9]{20}'),
            'payment_intent_id' => $this->faker->optional(0.8)->regexify('pi_[A-Za-z0-9]{24}'),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'metadata' => $this->faker->optional(0.5)->randomElement([
                ['source' => 'web', 'ip_address' => $this->faker->ipv4],
                ['source' => 'mobile', 'device' => 'iOS'],
                ['promo_code' => 'SAVE10', 'discount_applied' => true],
                null,
            ]),
        ];
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => \App\Enums\TransactionStatusEnum::CONFIRMED->value,
            'payment_gateway_transaction_id' => $this->faker->regexify('[A-Z0-9]{20}'),
            'payment_intent_id' => $this->faker->regexify('pi_[A-Za-z0-9]{24}'),
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => \App\Enums\TransactionStatusEnum::PENDING_PAYMENT->value,
            'payment_gateway_transaction_id' => null,
            'payment_intent_id' => $this->faker->regexify('pi_[A-Za-z0-9]{24}'),
        ]);
    }

    /**
     * Indicate that the transaction failed.
     */
    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => \App\Enums\TransactionStatusEnum::FAILED_PAYMENT->value,
            'payment_gateway_transaction_id' => null,
            'payment_intent_id' => $this->faker->regexify('pi_[A-Za-z0-9]{24}'),
        ]);
    }
}
