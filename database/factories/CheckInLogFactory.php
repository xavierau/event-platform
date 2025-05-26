<?php

namespace Database\Factories;

use App\Enums\CheckInMethod;
use App\Enums\CheckInStatus;
use App\Models\Booking;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CheckInLog>
 */
class CheckInLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'event_occurrence_id' => EventOccurrence::factory(),
            'check_in_timestamp' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'method' => $this->faker->randomElement(CheckInMethod::cases()),
            'device_identifier' => $this->faker->optional(0.7)->uuid(),
            'location_description' => $this->faker->optional(0.8)->randomElement([
                'Main Entrance - Gate A',
                'Main Entrance - Gate B',
                'VIP Entrance',
                'Workshop Room 1 Checkpoint',
                'Workshop Room 2 Checkpoint',
                'Conference Hall Entry',
                'Exhibition Area Entry',
                'Side Entrance',
            ]),
            'operator_user_id' => $this->faker->optional(0.9)->randomElement(User::pluck('id')->toArray() ?: [User::factory()]),
            'status' => $this->faker->randomElement(CheckInStatus::cases()),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * Indicate that the check-in was successful.
     */
    public function successful(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
    }

    /**
     * Indicate that the check-in failed.
     */
    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => $this->faker->randomElement([
                CheckInStatus::FAILED_ALREADY_USED,
                CheckInStatus::FAILED_MAX_USES_REACHED,
                CheckInStatus::FAILED_INVALID_CODE,
                CheckInStatus::FAILED_NOT_YET_VALID,
                CheckInStatus::FAILED_EXPIRED,
            ]),
            'notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the check-in was done via QR scan.
     */
    public function qrScan(): static
    {
        return $this->state(fn(array $attributes) => [
            'method' => CheckInMethod::QR_SCAN,
            'device_identifier' => $this->faker->uuid(),
        ]);
    }

    /**
     * Indicate that the check-in was done manually.
     */
    public function manual(): static
    {
        return $this->state(fn(array $attributes) => [
            'method' => CheckInMethod::MANUAL_ENTRY,
            'device_identifier' => null,
            'notes' => 'Manual check-in by staff',
        ]);
    }
}
