<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TicketHold\Models\PurchaseLink>
 */
class PurchaseLinkFactory extends Factory
{
    protected $model = PurchaseLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'code' => Str::random(16),
            'ticket_hold_id' => TicketHold::factory(),
            'name' => $this->faker->optional(0.7)->words(2, true),
            'assigned_user_id' => null, // Anonymous by default
            'quantity_mode' => QuantityModeEnum::MAXIMUM,
            'quantity_limit' => $this->faker->numberBetween(1, 10),
            'quantity_purchased' => 0,
            'status' => LinkStatusEnum::ACTIVE,
            'expires_at' => $this->faker->optional(0.5)->dateTimeBetween('+1 day', '+1 month'),
            'revoked_at' => null,
            'revoked_by' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'metadata' => $this->faker->optional(0.2)->randomElement([
                ['source' => 'vip_invitation'],
                ['campaign' => 'early_bird'],
                ['department' => 'marketing'],
            ]),
        ];
    }

    /**
     * Indicate that the link is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LinkStatusEnum::ACTIVE,
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'revoked_at' => null,
            'revoked_by' => null,
        ]);
    }

    /**
     * Indicate that the link is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LinkStatusEnum::EXPIRED,
            'expires_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
            'revoked_at' => null,
            'revoked_by' => null,
        ]);
    }

    /**
     * Indicate that the link has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LinkStatusEnum::REVOKED,
            'revoked_at' => now(),
            'revoked_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the link is exhausted (all quantity used).
     */
    public function exhausted(): static
    {
        return $this->state(function (array $attributes) {
            $limit = $attributes['quantity_limit'] ?? $this->faker->numberBetween(1, 10);

            return [
                'status' => LinkStatusEnum::EXHAUSTED,
                'quantity_limit' => $limit,
                'quantity_purchased' => $limit,
            ];
        });
    }

    /**
     * Assign the link to a specific user.
     */
    public function withUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_user_id' => $user?->id ?? User::factory(),
        ]);
    }

    /**
     * Create an anonymous (open) link.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_user_id' => null,
        ]);
    }

    /**
     * Set the link to fixed quantity mode.
     */
    public function fixedQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_mode' => QuantityModeEnum::FIXED,
            'quantity_limit' => $quantity,
        ]);
    }

    /**
     * Set the link to maximum quantity mode.
     */
    public function maxQuantity(int $maxQuantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_mode' => QuantityModeEnum::MAXIMUM,
            'quantity_limit' => $maxQuantity,
        ]);
    }

    /**
     * Set the link to unlimited quantity mode.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_mode' => QuantityModeEnum::UNLIMITED,
            'quantity_limit' => null,
        ]);
    }

    /**
     * Create a link for a specific ticket hold.
     */
    public function forHold(TicketHold $hold): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_hold_id' => $hold->id,
        ]);
    }

    /**
     * Create a link without an expiration date (never expires).
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Create a link with some purchases already made.
     */
    public function withPurchases(int $purchasedQuantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_purchased' => $purchasedQuantity,
        ]);
    }

    /**
     * Create a partially used link.
     */
    public function partiallyUsed(): static
    {
        return $this->state(function (array $attributes) {
            $limit = $attributes['quantity_limit'] ?? $this->faker->numberBetween(5, 20);
            $purchased = $this->faker->numberBetween(1, $limit - 1);

            return [
                'quantity_mode' => QuantityModeEnum::MAXIMUM,
                'quantity_limit' => $limit,
                'quantity_purchased' => $purchased,
            ];
        });
    }
}
