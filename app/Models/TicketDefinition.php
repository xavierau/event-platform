<?php

namespace App\Models;

use App\Enums\TicketDefinitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketDefinition extends Model
{
    use HasFactory, SoftDeletes, HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'total_quantity',
        'availability_window_start',
        'availability_window_end',
        'availability_window_start_utc',
        'availability_window_end_utc',
        'min_per_order',
        'max_per_order',
        'status',
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
        'availability_window_start' => 'string',
        'availability_window_end' => 'string',
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

    public function getPublicData(): array
    {
        $effectivePrice = $this->pivot->price_override ?? $this->price;

        // Calculate available quantity for this occurrence
        $quantityForOccurrence = $this->pivot->quantity_for_occurrence;
        $availableQuantity = $quantityForOccurrence ?? $this->quantity_available;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'price' => $effectivePrice / 100, // Convert from cents to currency units
            'max_per_order' => $this->max_per_order,
            'min_per_order' => $this->min_per_order,
            'quantity_available' => $availableQuantity,
        ];
    }




    // Removed eventOccurrence() belongsTo relationship
    // Removed event() relationship

    // public function creator() // Assuming these are handled by a global scope or trait if needed
    // {
    //     return $this->belongsTo(User::class, 'created_by');
    // }

    // public function updater() // Assuming these are handled by a global scope or trait if needed
    // {
    //     return $this->belongsTo(User::class, 'updated_by');
    // }
}
