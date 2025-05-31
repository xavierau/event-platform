<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Wallet\Models\WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WalletTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'transaction_type' => $this->faker->randomElement(WalletTransactionType::cases()),
            'amount' => $this->faker->numberBetween(1, 1000),
            'description' => $this->faker->sentence(),
            'reference_type' => null,
            'reference_id' => null,
            'metadata' => null,
        ];
    }

    /**
     * Create an earning points transaction.
     */
    public function earnPoints(int $amount = null): static
    {
        return $this->state(fn(array $attributes) => [
            'transaction_type' => WalletTransactionType::EARN_POINTS,
            'amount' => $amount ?? $this->faker->numberBetween(10, 500),
            'description' => 'Points earned',
        ]);
    }

    /**
     * Create a spending points transaction.
     */
    public function spendPoints(int $amount = null): static
    {
        return $this->state(fn(array $attributes) => [
            'transaction_type' => WalletTransactionType::SPEND_POINTS,
            'amount' => $amount ?? $this->faker->numberBetween(10, 500),
            'description' => 'Points spent',
        ]);
    }

    /**
     * Create an earning kill points transaction.
     */
    public function earnKillPoints(int $amount = null): static
    {
        return $this->state(fn(array $attributes) => [
            'transaction_type' => WalletTransactionType::EARN_KILL_POINTS,
            'amount' => $amount ?? $this->faker->numberBetween(5, 100),
            'description' => 'Kill points earned',
        ]);
    }

    /**
     * Create a spending kill points transaction.
     */
    public function spendKillPoints(int $amount = null): static
    {
        return $this->state(fn(array $attributes) => [
            'transaction_type' => WalletTransactionType::SPEND_KILL_POINTS,
            'amount' => $amount ?? $this->faker->numberBetween(5, 100),
            'description' => 'Kill points spent',
        ]);
    }

    /**
     * Create a transfer transaction.
     */
    public function transfer(bool $incoming = true, int $amount = null): static
    {
        return $this->state(fn(array $attributes) => [
            'transaction_type' => $incoming ? WalletTransactionType::TRANSFER_IN : WalletTransactionType::TRANSFER_OUT,
            'amount' => $amount ?? $this->faker->numberBetween(10, 200),
            'description' => $incoming ? 'Points received' : 'Points sent',
            'metadata' => [
                'transfer_type' => $incoming ? 'incoming' : 'outgoing',
                'other_user_id' => $this->faker->numberBetween(1, 100),
            ],
        ]);
    }
}
