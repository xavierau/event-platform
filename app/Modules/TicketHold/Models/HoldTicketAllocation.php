<?php

namespace App\Modules\TicketHold\Models;

use App\Models\TicketDefinition;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use Database\Factories\HoldTicketAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldTicketAllocation extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return HoldTicketAllocationFactory::new();
    }

    protected $fillable = [
        'ticket_hold_id',
        'ticket_definition_id',
        'allocated_quantity',
        'purchased_quantity',
        'pricing_mode',
        'custom_price',
        'discount_percentage',
    ];

    protected $casts = [
        'allocated_quantity' => 'integer',
        'purchased_quantity' => 'integer',
        'pricing_mode' => PricingModeEnum::class,
        'custom_price' => 'integer',
        'discount_percentage' => 'integer',
    ];

    // Relationships

    public function ticketHold(): BelongsTo
    {
        return $this->belongsTo(TicketHold::class);
    }

    public function ticketDefinition(): BelongsTo
    {
        return $this->belongsTo(TicketDefinition::class);
    }

    // Accessors

    /**
     * Get remaining quantity available for purchase.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->allocated_quantity - $this->purchased_quantity);
    }

    /**
     * Check if tickets are still available in this allocation.
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->remaining_quantity > 0;
    }

    // Methods

    /**
     * Calculate the effective price for this allocation.
     *
     * @param  int|null  $originalPrice  Override original price (for pivot price_override)
     * @return int Price in cents
     */
    public function calculateEffectivePrice(?int $originalPrice = null): int
    {
        $basePrice = $originalPrice ?? $this->ticketDefinition->price;

        return match ($this->pricing_mode) {
            PricingModeEnum::ORIGINAL => $basePrice,
            PricingModeEnum::FIXED => $this->custom_price ?? $basePrice,
            PricingModeEnum::PERCENTAGE_DISCOUNT => (int) round(
                $basePrice * (1 - ($this->discount_percentage / 100))
            ),
            PricingModeEnum::FREE => 0,
        };
    }

    /**
     * Increment purchased quantity after a successful purchase.
     *
     * IMPORTANT: This method assumes the caller has already acquired a lock
     * on this record via lockForUpdate() within a transaction. If called
     * outside of a locked transaction context, this method will acquire
     * its own lock to ensure atomic updates.
     */
    public function recordPurchase(int $quantity): void
    {
        // Use a callback to ensure we're working with the latest locked state
        $callback = function () use ($quantity) {
            // Re-fetch with lock to ensure atomic read-modify-write
            $lockedSelf = static::lockForUpdate()->find($this->id);

            if (! $lockedSelf) {
                return;
            }

            // Perform atomic increment using the locked instance
            $lockedSelf->increment('purchased_quantity', $quantity);

            // Sync the current instance with the updated values
            $this->purchased_quantity = $lockedSelf->purchased_quantity;

            // Check if the hold is exhausted (all allocations fully purchased)
            $lockedSelf->ticketHold->markExhausted();
        };

        // Check if we're already in a transaction
        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
            $callback();
        } else {
            \Illuminate\Support\Facades\DB::transaction($callback);
        }
    }
}
