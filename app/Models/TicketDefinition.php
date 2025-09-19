<?php

namespace App\Models;

use App\Enums\TicketDefinitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Modules\Membership\Models\MembershipLevel;

class TicketDefinition extends Model
{
    use HasFactory, SoftDeletes, HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'availability_window_start',
        'availability_window_end',
        'availability_window_start_utc',
        'availability_window_end_utc',
        'max_per_order',
        'min_per_order',
        'status',
        'timezone',
        'total_quantity',
        'metadata',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price' => 'integer',
        'total_quantity' => 'integer',
        'availability_window_start' => 'datetime',
        'availability_window_end' => 'datetime',
        'availability_window_start_utc' => 'datetime',
        'availability_window_end_utc' => 'datetime',
        'min_per_order' => 'integer',
        'max_per_order' => 'integer',
        'status' => TicketDefinitionStatus::class,
        'metadata' => 'json',
    ];

    protected static function booted()
    {
        static::creating(function ($ticketDefinition) {
            if (empty($ticketDefinition->currency)) {
                $ticketDefinition->currency = strtolower(config('cashier.currency'));
            }
        });

        static::updating(function ($ticketDefinition) {
            if (empty($ticketDefinition->currency)) {
                $ticketDefinition->currency = strtolower(config('cashier.currency'));
            }
        });
    }

    /**
     * T
     * e EventOccurrences that this TicketDefinition is available for.
     */
    public function eventOccurrences(): BelongsToMany
    {
        return $this->belongsToMany(EventOccurrence::class, 'event_occurrence_ticket_definition')
            ->withPivot(['quantity_for_occurrence', 'price_override', 'availability_status'])
            ->withTimestamps();
    }

    public function getQuantityAvailableAttribute(): int
    {
        // If total_quantity is not set (null), it signifies unlimited availability.
        // PHP_INT_MAX is used to represent this concept for an integer return type,
        // ensuring compatibility with consumers that expect a number (e.g., frontend).
        if (is_null($this->total_quantity)) {
            return PHP_INT_MAX;
        }

        // For finite quantities:
        // Start with the total quantity defined for this ticket definition.
        // The original comments regarding pivot data (e.g., quantity_for_occurrence)
        // would apply if such pivot data were being accessed and used to override total_quantity here.
        // This accessor, by default, uses the TicketDefinition's own total_quantity.
        $initialStock = $this->total_quantity;

        // Sum all quantities from bookings made for this ticket definition ID.
        // As per the Booking model, each Booking record has its quantity typically set to 1.
        $bookedQuantity = Booking::where('ticket_definition_id', $this->id)
            ->sum('quantity');

        return $initialStock - $bookedQuantity;
    }

    /**
     * The membership levels that have discounts for this ticket definition.
     */
    public function membershipDiscounts(): BelongsToMany
    {
        return $this->belongsToMany(MembershipLevel::class, 'ticket_definition_membership_discounts')
            ->withPivot(['discount_type', 'discount_value'])
            ->withTimestamps();
    }

    /**
     * Check if this ticket definition has a discount for the given membership level.
     */
    public function hasMembershipDiscount(MembershipLevel $membershipLevel): bool
    {
        return $this->membershipDiscounts()->where('membership_level_id', $membershipLevel->id)->exists();
    }

    /**
     * Get the membership price for a given user.
     * Returns the regular price if no membership or discount applies.
     */
    public function getMembershipPrice(User $user): int
    {
        $activeMembershipLevel = $user->getActiveMembershipLevel();

        if (!$activeMembershipLevel) {
            return $this->price;
        }

        // Check if this ticket has a discount for the user's membership level
        $discount = $this->membershipDiscounts()
            ->where('membership_level_id', $activeMembershipLevel->id)
            ->first();

        if (!$discount) {
            return $this->price;
        }

        return $this->applyDiscount($this->price, $discount->pivot->discount_type, $discount->pivot->discount_value);
    }

    /**
     * Apply discount to a price based on type and value.
     */
    protected function applyDiscount(int $basePrice, string $discountType, int $discountValue): int
    {
        if ($discountType === 'percentage') {
            $discountAmount = round($basePrice * ($discountValue / 100));
            return max(0, $basePrice - $discountAmount);
        } elseif ($discountType === 'fixed') {
            return max(0, $basePrice - $discountValue);
        }

        return $basePrice;
    }

    public function getPublicData(?User $user = null): array
    {
        $effectivePrice = $this->pivot->price_override ?? $this->price;

        // Calculate available quantity for this occurrence
        $quantityForOccurrence = $this->pivot->quantity_for_occurrence;
        $availableQuantity = $quantityForOccurrence ?? $this->quantity_available;

        $publicData = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'price' => $effectivePrice / 100, // Convert from cents to currency units
            'max_per_order' => $this->max_per_order,
            'min_per_order' => $this->min_per_order,
            'quantity_available' => $availableQuantity,
        ];

        // Include membership pricing if user is provided
        if ($user) {
            $membershipPrice = $this->getMembershipPrice($user);
            $hasMembershipDiscount = $membershipPrice < $this->price;

            $publicData['membership_price'] = $membershipPrice / 100; // Convert from cents
            $publicData['has_membership_discount'] = $hasMembershipDiscount;
        }

        return $publicData;
    }
}
