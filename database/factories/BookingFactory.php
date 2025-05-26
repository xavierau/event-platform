<?php

namespace Database\Factories;

use App\Helpers\QrCodeHelper;
use App\Models\Event;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'ticket_definition_id' => TicketDefinition::factory(),
            'event_id' => Event::factory(),
            'booking_number' => (string) Str::uuid(),
            'quantity' => 1, // Always 1 as per project requirements
            'price_at_booking' => $this->faker->numberBetween(1000, 20000), // Price in cents
            'currency_at_booking' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD']),
            'status' => $this->faker->randomElement(['confirmed', 'cancelled', 'used']),
            'metadata' => $this->faker->optional(0.3)->randomElement([
                ['seat_number' => $this->faker->randomNumber(3)],
                ['special_requirements' => 'Wheelchair accessible'],
                ['notes' => $this->faker->sentence()],
                null,
            ]),
            'qr_code_identifier' => QrCodeHelper::generate(),
            'max_allowed_check_ins' => $this->faker->numberBetween(1, 3),
        ];
    }



    /**
     * Indicate that the booking is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Indicate that the booking is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the booking is used.
     */
    public function used(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'used',
        ]);
    }

    /**
     * Set a specific QR code identifier.
     */
    public function withQrCode(string $qrCode): static
    {
        return $this->state(fn(array $attributes) => [
            'qr_code_identifier' => $qrCode,
        ]);
    }

    /**
     * Set max allowed check-ins.
     */
    public function withMaxCheckIns(int $maxCheckIns): static
    {
        return $this->state(fn(array $attributes) => [
            'max_allowed_check_ins' => $maxCheckIns,
        ]);
    }
}
