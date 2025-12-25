<?php

namespace App\Modules\TicketHold\Models;

use App\Models\User;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use Database\Factories\PurchaseLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseLink extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PurchaseLinkFactory::new();
    }

    protected $fillable = [
        'uuid',
        'ticket_hold_id',
        'code',
        'name',
        'assigned_user_id',
        'quantity_mode',
        'quantity_limit',
        'quantity_purchased',
        'status',
        'expires_at',
        'revoked_at',
        'revoked_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'quantity_mode' => QuantityModeEnum::class,
        'quantity_limit' => 'integer',
        'quantity_purchased' => 'integer',
        'status' => LinkStatusEnum::class,
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseLink $link) {
            $link->uuid = $link->uuid ?? Str::uuid()->toString();
            $link->code = $link->code ?? self::generateUniqueCode();
        });
    }

    // Relationships

    public function ticketHold(): BelongsTo
    {
        return $this->belongsTo(TicketHold::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(PurchaseLinkAccess::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PurchaseLinkPurchase::class);
    }

    // Scopes

    /**
     * Scope to filter active links.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', LinkStatusEnum::ACTIVE);
    }

    /**
     * Scope to filter by code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope to filter links for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Scope to filter anonymous links.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAnonymous($query)
    {
        return $query->whereNull('assigned_user_id');
    }

    // Accessors

    /**
     * Check if this is an anonymous (open) link.
     */
    public function getIsAnonymousAttribute(): bool
    {
        return is_null($this->assigned_user_id);
    }

    /**
     * Check if the link has expired based on expiration date.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get remaining quantity available for purchase.
     * Returns null for unlimited links.
     */
    public function getRemainingQuantityAttribute(): ?int
    {
        if ($this->quantity_mode === QuantityModeEnum::UNLIMITED) {
            return null; // Unlimited
        }

        return max(0, $this->quantity_limit - $this->quantity_purchased);
    }

    /**
     * Check if the link is currently usable.
     */
    public function getIsUsableAttribute(): bool
    {
        if (! $this->status->isUsable()) {
            return false;
        }
        if ($this->is_expired) {
            return false;
        }
        if (! $this->ticketHold->is_usable) {
            return false;
        }
        if ($this->remaining_quantity === 0) {
            return false;
        }

        return true;
    }

    /**
     * Get the full URL for this purchase link.
     */
    public function getFullUrlAttribute(): string
    {
        return route('purchase-link.show', ['code' => $this->code]);
    }

    // Methods

    /**
     * Generate a unique code for a new purchase link.
     *
     * @param  int  $maxAttempts  Maximum number of attempts before throwing an exception
     *
     * @throws \RuntimeException If unable to generate a unique code within max attempts
     */
    public static function generateUniqueCode(int $maxAttempts = 10): string
    {
        $attempts = 0;

        do {
            $code = Str::random(16);
            $attempts++;

            if ($attempts > $maxAttempts) {
                throw new \RuntimeException(
                    "Unable to generate a unique purchase link code after {$maxAttempts} attempts."
                );
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check if a user can use this link.
     */
    public function canBeUsedByUser(?User $user): bool
    {
        // Anonymous link - anyone can use
        if ($this->is_anonymous) {
            return true;
        }

        // User-tied link - must match assigned user
        return $user && $user->id === $this->assigned_user_id;
    }

    /**
     * Check if the requested quantity can be purchased through this link.
     */
    public function canPurchaseQuantity(int $quantity): bool
    {
        if ($this->quantity_mode === QuantityModeEnum::UNLIMITED) {
            return true;
        }

        if ($this->quantity_mode === QuantityModeEnum::FIXED) {
            // For fixed mode, must purchase exactly the remaining quantity
            return $quantity === $this->remaining_quantity;
        }

        // Maximum mode
        return $quantity <= $this->remaining_quantity;
    }

    /**
     * Revoke this purchase link.
     */
    public function revoke(User $revokedBy): void
    {
        $this->update([
            'status' => LinkStatusEnum::REVOKED,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy->id,
        ]);
    }

    /**
     * Record a purchase through this link.
     *
     * IMPORTANT: This method assumes the caller has already acquired a lock
     * on this record via lockForUpdate() within a transaction. If called
     * outside of a locked transaction context, this method will acquire
     * its own lock to ensure atomic updates.
     */
    public function recordPurchase(int $quantity): void
    {
        // Use a callback to ensure we're working with the latest locked state
        // If already in a transaction with lock, this will be a no-op refresh
        // If not, this ensures we have the latest data
        $callback = function () use ($quantity) {
            // Re-fetch with lock to ensure atomic read-modify-write
            $lockedSelf = static::lockForUpdate()->find($this->id);

            if (! $lockedSelf) {
                return;
            }

            // Perform atomic increment using the locked instance
            $lockedSelf->increment('quantity_purchased', $quantity);

            // Check if exhausted after increment
            if ($lockedSelf->quantity_mode !== QuantityModeEnum::UNLIMITED) {
                $lockedSelf->refresh();
                if ($lockedSelf->remaining_quantity <= 0) {
                    $lockedSelf->update(['status' => LinkStatusEnum::EXHAUSTED]);
                }
            }

            // Sync the current instance with the updated values
            $this->quantity_purchased = $lockedSelf->quantity_purchased;
            $this->status = $lockedSelf->status;
        };

        // Check if we're already in a transaction
        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
            $callback();
        } else {
            \Illuminate\Support\Facades\DB::transaction($callback);
        }
    }

    /**
     * Check and update expiration status if expired.
     */
    public function checkAndUpdateExpiration(): void
    {
        if ($this->is_expired && $this->status === LinkStatusEnum::ACTIVE) {
            $this->update(['status' => LinkStatusEnum::EXPIRED]);
        }
    }
}
