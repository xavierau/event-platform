<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TicketHold\Models\PurchaseLinkPurchase>
 */
class PurchaseLinkPurchaseFactory extends Factory
{
    protected $model = PurchaseLinkPurchase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalPrice = $this->faker->numberBetween(2000, 10000); // 20.00 to 100.00
        $unitPrice = $this->faker->numberBetween(0, $originalPrice);

        return [
            'purchase_link_id' => PurchaseLink::factory(),
            'booking_id' => Booking::factory(),
            'transaction_id' => $this->faker->optional(0.9)->passthrough(Transaction::factory()),
            'user_id' => $this->faker->optional(0.8)->passthrough(User::factory()),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => $unitPrice,
            'original_price' => $originalPrice,
            'currency' => 'hkd',
            'access_id' => $this->faker->optional(0.7)->passthrough(PurchaseLinkAccess::factory()),
        ];
    }

    /**
     * Create a purchase at original price (no discount).
     */
    public function atOriginalPrice(?int $priceInCents = null): static
    {
        $price = $priceInCents ?? $this->faker->numberBetween(2000, 10000);

        return $this->state(fn (array $attributes) => [
            'unit_price' => $price,
            'original_price' => $price,
        ]);
    }

    /**
     * Create a purchase with a discount.
     */
    public function withDiscount(int $discountPercentage): static
    {
        return $this->state(function (array $attributes) use ($discountPercentage) {
            $originalPrice = $attributes['original_price'] ?? $this->faker->numberBetween(2000, 10000);
            $unitPrice = (int) round($originalPrice * (1 - $discountPercentage / 100));

            return [
                'original_price' => $originalPrice,
                'unit_price' => $unitPrice,
            ];
        });
    }

    /**
     * Create a free purchase (complimentary ticket).
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => 0,
        ]);
    }

    /**
     * Create a purchase for a specific purchase link.
     */
    public function forLink(PurchaseLink $link): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_link_id' => $link->id,
        ]);
    }

    /**
     * Create a purchase for a specific booking.
     */
    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_id' => $booking->id,
        ]);
    }

    /**
     * Create a purchase for a specific transaction.
     */
    public function forTransaction(Transaction $transaction): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_id' => $transaction->id,
        ]);
    }

    /**
     * Create a purchase by a specific user.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a purchase linked to a specific access record.
     */
    public function fromAccess(PurchaseLinkAccess $access): static
    {
        return $this->state(fn (array $attributes) => [
            'access_id' => $access->id,
        ]);
    }

    /**
     * Create a purchase with a specific quantity.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    /**
     * Create a purchase with a specific currency.
     */
    public function withCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => strtolower($currency),
        ]);
    }

    /**
     * Create a purchase without a transaction (e.g., free ticket).
     */
    public function withoutTransaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_id' => null,
        ]);
    }

    /**
     * Create an anonymous purchase (no user).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
