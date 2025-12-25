<?php

namespace App\Modules\TicketHold\Models;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use Database\Factories\TicketHoldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TicketHold extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return TicketHoldFactory::new();
    }

    protected $fillable = [
        'uuid',
        'event_occurrence_id',
        'organizer_id',
        'created_by',
        'name',
        'description',
        'internal_notes',
        'status',
        'expires_at',
        'released_at',
        'released_by',
    ];

    protected $appends = [
        'is_expired',
        'is_usable',
    ];

    protected $casts = [
        'status' => HoldStatusEnum::class,
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketHold $hold) {
            $hold->uuid = $hold->uuid ?? Str::uuid()->toString();
        });
    }

    // Relationships

    public function eventOccurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(HoldTicketAllocation::class);
    }

    public function purchaseLinks(): HasMany
    {
        return $this->hasMany(PurchaseLink::class);
    }

    // Scopes

    /**
     * Scope to filter active holds.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', HoldStatusEnum::ACTIVE);
    }

    /**
     * Scope to filter holds for a specific occurrence.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForOccurrence($query, int $occurrenceId)
    {
        return $query->where('event_occurrence_id', $occurrenceId);
    }

    /**
     * Scope to filter holds for a specific organizer.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('organizer_id', $organizerId);
    }

    /**
     * Scope to filter holds that are not expired.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to eager load statistics using aggregate queries instead of N+1 accessors.
     *
     * This avoids the N+1 query problem that occurs when accessing total_allocated,
     * total_purchased, total_remaining, and purchase_links_count as model accessors.
     */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query
            ->withSum('allocations as total_allocated', 'allocated_quantity')
            ->withSum('allocations as total_purchased', 'purchased_quantity')
            ->withCount('purchaseLinks as purchase_links_count');
    }

    // Accessors

    /**
     * Get total allocated quantity across all allocations.
     *
     * When using withStats() scope, this value is pre-loaded via withSum().
     * Falls back to calculating from loaded relations if not pre-loaded.
     */
    public function getTotalAllocatedAttribute(): int
    {
        // Check if the value was pre-loaded via withSum/loadSum
        if (array_key_exists('total_allocated', $this->attributes)) {
            return (int) $this->attributes['total_allocated'];
        }

        return $this->allocations->sum('allocated_quantity');
    }

    /**
     * Get total purchased quantity across all allocations.
     *
     * When using withStats() scope, this value is pre-loaded via withSum().
     * Falls back to calculating from loaded relations if not pre-loaded.
     */
    public function getTotalPurchasedAttribute(): int
    {
        // Check if the value was pre-loaded via withSum/loadSum
        if (array_key_exists('total_purchased', $this->attributes)) {
            return (int) $this->attributes['total_purchased'];
        }

        return $this->allocations->sum('purchased_quantity');
    }

    /**
     * Get total remaining quantity across all allocations.
     */
    public function getTotalRemainingAttribute(): int
    {
        return $this->total_allocated - $this->total_purchased;
    }

    /**
     * Get the count of purchase links for this hold.
     *
     * When using withStats() scope, this value is pre-loaded via withCount().
     * Falls back to querying the relationship if not pre-loaded.
     */
    public function getPurchaseLinksCountAttribute(): int
    {
        // Check if the value was pre-loaded via withCount/loadCount
        if (array_key_exists('purchase_links_count', $this->attributes)) {
            return (int) $this->attributes['purchase_links_count'];
        }

        return $this->purchaseLinks()->count();
    }

    /**
     * Check if the hold has expired based on expiration date.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the hold is currently usable (active and not expired).
     */
    public function getIsUsableAttribute(): bool
    {
        return $this->status->isUsable() && ! $this->is_expired;
    }

    // Methods

    /**
     * Release the hold, making tickets available for public sale.
     */
    public function release(User $releasedBy): void
    {
        $this->update([
            'status' => HoldStatusEnum::RELEASED,
            'released_at' => now(),
            'released_by' => $releasedBy->id,
        ]);
    }

    /**
     * Mark the hold as exhausted if no remaining tickets.
     */
    public function markExhausted(): void
    {
        if ($this->total_remaining <= 0) {
            $this->update(['status' => HoldStatusEnum::EXHAUSTED]);
        }
    }

    /**
     * Check and update expiration status if expired.
     */
    public function checkAndUpdateExpiration(): void
    {
        if ($this->is_expired && $this->status === HoldStatusEnum::ACTIVE) {
            $this->update(['status' => HoldStatusEnum::EXPIRED]);
        }
    }
}
