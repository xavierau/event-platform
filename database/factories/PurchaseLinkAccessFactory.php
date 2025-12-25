<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TicketHold\Models\PurchaseLinkAccess>
 */
class PurchaseLinkAccessFactory extends Factory
{
    protected $model = PurchaseLinkAccess::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_link_id' => PurchaseLink::factory(),
            'user_id' => $this->faker->optional(0.6)->passthrough(User::factory()),
            'ip_address' => $this->faker->optional(0.9)->ipv4(),
            'user_agent' => $this->faker->optional(0.8)->userAgent(),
            'referer' => $this->faker->optional(0.5)->url(),
            'session_id' => $this->faker->optional(0.7)->regexify('[a-zA-Z0-9]{40}'),
            'resulted_in_purchase' => false,
            'accessed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the access resulted in a purchase.
     */
    public function withPurchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'resulted_in_purchase' => true,
        ]);
    }

    /**
     * Indicate that the access did not result in a purchase.
     */
    public function withoutPurchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'resulted_in_purchase' => false,
        ]);
    }

    /**
     * Create an access for a specific purchase link.
     */
    public function forLink(PurchaseLink $link): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_link_id' => $link->id,
        ]);
    }

    /**
     * Create an access for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create an anonymous access (no user).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Create an access at a specific time.
     */
    public function accessedAt(\DateTimeInterface|string $datetime): static
    {
        return $this->state(fn (array $attributes) => [
            'accessed_at' => $datetime,
        ]);
    }

    /**
     * Create an access with a specific IP address.
     */
    public function fromIp(string $ipAddress): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Create an access from a specific referer.
     */
    public function fromReferer(string $referer): static
    {
        return $this->state(fn (array $attributes) => [
            'referer' => $referer,
        ]);
    }

    /**
     * Create a recent access (within the last hour).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'accessed_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }
}
