<?php

namespace Database\Factories;

use App\Models\TicketDefinition;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TicketHold\Models\HoldTicketAllocation>
 */
class HoldTicketAllocationFactory extends Factory
{
    protected $model = HoldTicketAllocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_hold_id' => TicketHold::factory(),
            'ticket_definition_id' => TicketDefinition::factory(),
            'allocated_quantity' => $this->faker->numberBetween(5, 50),
            'purchased_quantity' => 0,
            'pricing_mode' => PricingModeEnum::ORIGINAL,
            'custom_price' => null,
            'discount_percentage' => null,
        ];
    }

    /**
     * Set allocation to use original ticket price.
     */
    public function originalPrice(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingModeEnum::ORIGINAL,
            'custom_price' => null,
            'discount_percentage' => null,
        ]);
    }

    /**
     * Set allocation to use a fixed custom price.
     */
    public function fixedPrice(?int $priceInCents = null): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingModeEnum::FIXED,
            'custom_price' => $priceInCents ?? $this->faker->numberBetween(500, 5000),
            'discount_percentage' => null,
        ]);
    }

    /**
     * Set allocation to use a percentage discount.
     */
    public function discounted(?int $percentage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingModeEnum::PERCENTAGE_DISCOUNT,
            'custom_price' => null,
            'discount_percentage' => $percentage ?? $this->faker->numberBetween(10, 50),
        ]);
    }

    /**
     * Set allocation to be free (complimentary).
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingModeEnum::FREE,
            'custom_price' => null,
            'discount_percentage' => null,
        ]);
    }

    /**
     * Set allocation for a specific ticket hold.
     */
    public function forHold(TicketHold $hold): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_hold_id' => $hold->id,
        ]);
    }

    /**
     * Set allocation for a specific ticket definition.
     */
    public function forTicketDefinition(TicketDefinition $ticketDefinition): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_definition_id' => $ticketDefinition->id,
        ]);
    }

    /**
     * Set a specific allocated quantity.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'allocated_quantity' => $quantity,
        ]);
    }

    /**
     * Set some tickets as already purchased.
     */
    public function withPurchased(int $purchasedQuantity): static
    {
        return $this->state(fn (array $attributes) => [
            'purchased_quantity' => $purchasedQuantity,
        ]);
    }

    /**
     * Create a fully purchased allocation (no remaining tickets).
     */
    public function fullyPurchased(): static
    {
        return $this->state(function (array $attributes) {
            $allocated = $attributes['allocated_quantity'] ?? $this->faker->numberBetween(5, 50);

            return [
                'allocated_quantity' => $allocated,
                'purchased_quantity' => $allocated,
            ];
        });
    }

    /**
     * Create a partially purchased allocation.
     */
    public function partiallyPurchased(): static
    {
        return $this->state(function (array $attributes) {
            $allocated = $attributes['allocated_quantity'] ?? $this->faker->numberBetween(10, 50);
            $purchased = $this->faker->numberBetween(1, $allocated - 1);

            return [
                'allocated_quantity' => $allocated,
                'purchased_quantity' => $purchased,
            ];
        });
    }
}
