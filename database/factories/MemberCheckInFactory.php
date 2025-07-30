<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemberCheckIn>
 */
class MemberCheckInFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'scanned_by_user_id' => \App\Models\User::factory(),
            'scanned_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'location' => fake()->optional(0.7)->randomElement([
                'Main Entrance',
                'Registration Desk',
                'Conference Hall',
                'Exhibition Area',
                'VIP Lounge',
            ]),
            'notes' => fake()->optional(0.3)->sentence(),
            'device_identifier' => fake()->optional(0.8)->regexify('SCANNER-[0-9]{3}'),
            'membership_data' => function (array $attributes) {
                $user = \App\Models\User::find($attributes['user_id']);
                return [
                    'userId' => $user->id,
                    'userName' => $user->name,
                    'email' => $user->email,
                    'membershipLevel' => fake()->randomElement(['Standard', 'Premium', 'VIP']),
                    'membershipStatus' => fake()->randomElement(['Active', 'Free']),
                    'expiresAt' => fake()->dateTimeBetween('now', '+1 year')->format('F j, Y'),
                    'timestamp' => fake()->iso8601(),
                ];
            },
        ];
    }

    /**
     * Create a check-in for a specific member.
     */
    public function forMember(\App\Models\User $member): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $member->id,
            'membership_data' => [
                'userId' => $member->id,
                'userName' => $member->name,
                'email' => $member->email,
                'membershipLevel' => fake()->randomElement(['Standard', 'Premium', 'VIP']),
                'membershipStatus' => 'Active',
                'expiresAt' => fake()->dateTimeBetween('now', '+1 year')->format('F j, Y'),
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Create a check-in performed by a specific scanner.
     */
    public function scannedBy(\App\Models\User $scanner): static
    {
        return $this->state(fn(array $attributes) => [
            'scanned_by_user_id' => $scanner->id,
        ]);
    }

    /**
     * Create a recent check-in.
     */
    public function recent(): static
    {
        return $this->state(fn(array $attributes) => [
            'scanned_at' => fake()->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Create a check-in with premium membership data.
     */
    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            $membershipData = $attributes['membership_data'];
            $membershipData['membershipLevel'] = 'Premium';
            $membershipData['membershipStatus'] = 'Active';
            
            return [
                'membership_data' => $membershipData,
            ];
        });
    }
}
