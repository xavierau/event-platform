<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Wallet\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Wallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'points_balance' => 0,
            'kill_points_balance' => 0,
            'total_points_earned' => 0,
            'total_points_spent' => 0,
            'total_kill_points_earned' => 0,
            'total_kill_points_spent' => 0,
        ];
    }

    /**
     * Create a wallet with some points balance.
     */
    public function withPoints(int $points = 100): static
    {
        return $this->state(fn(array $attributes) => [
            'points_balance' => $points,
            'total_points_earned' => $points,
        ]);
    }

    /**
     * Create a wallet with some kill points balance.
     */
    public function withKillPoints(int $killPoints = 50): static
    {
        return $this->state(fn(array $attributes) => [
            'kill_points_balance' => $killPoints,
            'total_kill_points_earned' => $killPoints,
        ]);
    }

    /**
     * Create a wallet with both points and kill points.
     */
    public function withBothPoints(int $points = 100, int $killPoints = 50): static
    {
        return $this->state(fn(array $attributes) => [
            'points_balance' => $points,
            'kill_points_balance' => $killPoints,
            'total_points_earned' => $points,
            'total_kill_points_earned' => $killPoints,
        ]);
    }
}
