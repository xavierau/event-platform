# Technical Specification: Ticket Hold & Custom Purchase Link Feature

**Document Version:** 1.0
**Date:** 2025-12-24
**Author:** Solution Architect
**Status:** Ready for Review

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Domain Model](#2-domain-model)
3. [Database Schema](#3-database-schema)
4. [Enums](#4-enums)
5. [Eloquent Models](#5-eloquent-models)
6. [Data Transfer Objects (DTOs)](#6-data-transfer-objects-dtos)
7. [Service & Action Layer](#7-service--action-layer)
8. [API Endpoints](#8-api-endpoints)
9. [Authorization & Policies](#9-authorization--policies)
10. [Frontend Components](#10-frontend-components)
11. [Coupon Integration](#11-coupon-integration)
12. [Analytics & Reporting](#12-analytics--reporting)
13. [Edge Cases & Error Handling](#13-edge-cases--error-handling)
14. [Security Considerations](#14-security-considerations)
15. [Implementation Phases](#15-implementation-phases)
16. [Task Breakdown for Delegation](#16-task-breakdown-for-delegation)

---

## 1. Executive Summary

### 1.1 Purpose

This feature allows platform administrators and organizers to:
1. **Reserve (hold) tickets** from public sale for specific events/occurrences
2. **Generate custom purchase links** that grant access to these reserved tickets
3. **Configure flexible pricing** (original, discounted, premium, or free)
4. **Track link usage** with analytics on opens, conversions, and purchases

### 1.2 Key Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Module Location | `app/Modules/TicketHold/` | Follows existing module pattern (Coupon, Membership) |
| Link Assignment | Hybrid (user-tied OR anonymous) | Maximum flexibility per user requirements |
| Link Reusability | Reusable with configurable limits | Supports multiple purchase scenarios |
| Pricing | Configurable per hold | Supports comps, discounts, premiums |
| Expiration | Both manual and time-based | Dual mechanism for flexibility |
| Inventory Model | Separate `held_quantity` tracking | Non-invasive to existing `TicketDefinition` inventory |

### 1.3 Glossary

| Term | Definition |
|------|------------|
| **Ticket Hold** | A reservation of N tickets from public inventory for private distribution |
| **Purchase Link** | A unique URL that grants access to purchase held tickets |
| **Hold Pool** | The quantity of tickets reserved under a specific hold |
| **Conversion** | When a link access results in a completed purchase |

---

## 2. Domain Model

### 2.1 Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────────┐       ┌──────────────────┐
│     Event       │──1:N──│   EventOccurrence   │──M:N──│ TicketDefinition │
└─────────────────┘       └─────────────────────┘       └──────────────────┘
                                    │                            │
                                    │                            │
                                   1│                           1│
                                    │                            │
                                    ▼                            ▼
                          ┌─────────────────────┐      ┌─────────────────────┐
                          │    TicketHold       │──────│  HoldTicketAlloc    │
                          │  (aggregate root)   │ 1:N  │  (pivot: hold ↔     │
                          └─────────────────────┘      │   ticket_definition)│
                                    │                  └─────────────────────┘
                                    │
                                   1│
                                    │
                                    ▼
                          ┌─────────────────────┐
                          │   PurchaseLink      │
                          │ (unique access URL) │
                          └─────────────────────┘
                                    │
                           ┌───────┴───────┐
                          1│              1│
                           ▼               ▼
                 ┌──────────────┐  ┌────────────────┐
                 │ LinkAccess   │  │  LinkPurchase  │
                 │ (analytics)  │  │  (tracks buys) │
                 └──────────────┘  └────────────────┘
```

### 2.2 Aggregate Boundaries

**TicketHold Aggregate:**
- Root: `TicketHold`
- Entities: `HoldTicketAllocation`, `PurchaseLink`
- Value Objects: `PricingConfig`, `QuantityConfig`, `ExpirationConfig`

**Invariants:**
1. Total allocated quantity across all tickets cannot exceed hold quantity
2. Purchase link can only be created if hold is active
3. Purchases through link cannot exceed link's quantity limit
4. Purchases cannot exceed hold's remaining available quantity

### 2.3 Domain Events (for future extensibility)

```php
TicketHoldCreated::class
TicketHoldReleased::class
PurchaseLinkGenerated::class
PurchaseLinkAccessed::class
PurchaseLinkPurchaseCompleted::class
HoldExpired::class
```

---

## 3. Database Schema

### 3.1 Migration: `create_ticket_holds_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main ticket holds table
        Schema::create('ticket_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Ownership
            $table->foreignId('event_occurrence_id')
                ->constrained('event_occurrences')
                ->cascadeOnDelete();
            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('organizers')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // Hold details
            $table->string('name'); // e.g., "VIP Press Allocation", "Sponsor Block A"
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable(); // Admin-only notes

            // Status & Lifecycle
            $table->string('status')->default('active'); // active, expired, released, exhausted
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['event_occurrence_id', 'status']);
            $table->index('expires_at');
            $table->index('organizer_id');
        });

        // Allocation of tickets to holds (which ticket types and quantities)
        Schema::create('hold_ticket_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_hold_id')
                ->constrained('ticket_holds')
                ->cascadeOnDelete();
            $table->foreignId('ticket_definition_id')
                ->constrained('ticket_definitions')
                ->cascadeOnDelete();

            // Quantities
            $table->unsignedInteger('allocated_quantity'); // Total reserved
            $table->unsignedInteger('purchased_quantity')->default(0); // Already bought

            // Pricing configuration
            $table->string('pricing_mode')->default('original'); // original, fixed, percentage_discount, free
            $table->unsignedInteger('custom_price')->nullable(); // In cents (for fixed mode)
            $table->unsignedInteger('discount_percentage')->nullable(); // 0-100 (for percentage mode)

            $table->timestamps();

            // Composite unique constraint
            $table->unique(['ticket_hold_id', 'ticket_definition_id'], 'hold_ticket_unique');

            // Index for availability checks
            $table->index(['ticket_definition_id', 'allocated_quantity', 'purchased_quantity'], 'ticket_availability_idx');
        });

        // Purchase links for accessing holds
        Schema::create('purchase_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('ticket_hold_id')
                ->constrained('ticket_holds')
                ->cascadeOnDelete();

            // Link identification
            $table->string('code', 32)->unique(); // URL-safe unique code
            $table->string('name')->nullable(); // Optional friendly name

            // User assignment (null = anonymous/open link)
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Quantity limits
            $table->string('quantity_mode')->default('unlimited'); // fixed, maximum, unlimited
            $table->unsignedInteger('quantity_limit')->nullable(); // For fixed/maximum modes
            $table->unsignedInteger('quantity_purchased')->default(0);

            // Status & Lifecycle
            $table->string('status')->default('active'); // active, expired, revoked, exhausted
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();

            // Metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Extensible data

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index(['ticket_hold_id', 'status']);
            $table->index('assigned_user_id');
            $table->index('expires_at');
        });

        // Analytics: Link access tracking
        Schema::create('purchase_link_accesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_link_id')
                ->constrained('purchase_links')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Access details
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referer', 500)->nullable();

            // Session tracking
            $table->string('session_id', 100)->nullable();
            $table->boolean('resulted_in_purchase')->default(false);

            $table->timestamp('accessed_at');
            $table->timestamps();

            // Indexes
            $table->index(['purchase_link_id', 'accessed_at']);
            $table->index('user_id');
        });

        // Tracks purchases made through links
        Schema::create('purchase_link_purchases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_link_id')
                ->constrained('purchase_links')
                ->cascadeOnDelete();
            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Purchase details
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price'); // Price paid per ticket (cents)
            $table->unsignedInteger('original_price'); // Original ticket price (cents)
            $table->string('currency', 3);

            // Link to access record (if tracked)
            $table->foreignId('access_id')
                ->nullable()
                ->constrained('purchase_link_accesses')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('purchase_link_id');
            $table->index('booking_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_link_purchases');
        Schema::dropIfExists('purchase_link_accesses');
        Schema::dropIfExists('purchase_links');
        Schema::dropIfExists('hold_ticket_allocations');
        Schema::dropIfExists('ticket_holds');
    }
};
```

### 3.2 Schema Diagram

```
ticket_holds
├── id (PK)
├── uuid (unique)
├── event_occurrence_id (FK → event_occurrences)
├── organizer_id (FK → organizers, nullable)
├── created_by (FK → users)
├── name
├── description
├── internal_notes
├── status (enum: active, expired, released, exhausted)
├── expires_at
├── released_at
├── released_by (FK → users)
├── timestamps
└── soft_deletes

hold_ticket_allocations
├── id (PK)
├── ticket_hold_id (FK → ticket_holds)
├── ticket_definition_id (FK → ticket_definitions)
├── allocated_quantity
├── purchased_quantity
├── pricing_mode (enum: original, fixed, percentage_discount, free)
├── custom_price (nullable, cents)
├── discount_percentage (nullable, 0-100)
└── timestamps

purchase_links
├── id (PK)
├── uuid (unique)
├── ticket_hold_id (FK → ticket_holds)
├── code (unique, 32 chars)
├── name (nullable)
├── assigned_user_id (FK → users, nullable)
├── quantity_mode (enum: fixed, maximum, unlimited)
├── quantity_limit (nullable)
├── quantity_purchased
├── status (enum: active, expired, revoked, exhausted)
├── expires_at (nullable)
├── revoked_at (nullable)
├── revoked_by (FK → users)
├── notes
├── metadata (JSON)
├── timestamps
└── soft_deletes

purchase_link_accesses
├── id (PK)
├── purchase_link_id (FK → purchase_links)
├── user_id (FK → users, nullable)
├── ip_address
├── user_agent
├── referer
├── session_id
├── resulted_in_purchase
├── accessed_at
└── timestamps

purchase_link_purchases
├── id (PK)
├── purchase_link_id (FK → purchase_links)
├── booking_id (FK → bookings)
├── transaction_id (FK → transactions)
├── user_id (FK → users)
├── quantity
├── unit_price
├── original_price
├── currency
├── access_id (FK → purchase_link_accesses, nullable)
└── timestamps
```

---

## 4. Enums

### 4.1 File: `app/Modules/TicketHold/Enums/HoldStatusEnum.php`

```php
<?php

namespace App\Modules\TicketHold\Enums;

enum HoldStatusEnum: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case RELEASED = 'released';
    case EXHAUSTED = 'exhausted';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::RELEASED => 'Released',
            self::EXHAUSTED => 'Exhausted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::EXPIRED => 'gray',
            self::RELEASED => 'blue',
            self::EXHAUSTED => 'orange',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }
}
```

### 4.2 File: `app/Modules/TicketHold/Enums/PricingModeEnum.php`

```php
<?php

namespace App\Modules\TicketHold\Enums;

enum PricingModeEnum: string
{
    case ORIGINAL = 'original';
    case FIXED = 'fixed';
    case PERCENTAGE_DISCOUNT = 'percentage_discount';
    case FREE = 'free';

    public function label(): string
    {
        return match ($this) {
            self::ORIGINAL => 'Original Price',
            self::FIXED => 'Custom Fixed Price',
            self::PERCENTAGE_DISCOUNT => 'Percentage Discount',
            self::FREE => 'Free (Complimentary)',
        };
    }

    public function requiresValue(): bool
    {
        return match ($this) {
            self::ORIGINAL, self::FREE => false,
            self::FIXED, self::PERCENTAGE_DISCOUNT => true,
        };
    }
}
```

### 4.3 File: `app/Modules/TicketHold/Enums/QuantityModeEnum.php`

```php
<?php

namespace App\Modules\TicketHold\Enums;

enum QuantityModeEnum: string
{
    case FIXED = 'fixed';
    case MAXIMUM = 'maximum';
    case UNLIMITED = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Exact Quantity',
            self::MAXIMUM => 'Up to Maximum',
            self::UNLIMITED => 'Unlimited (from pool)',
        };
    }

    public function requiresLimit(): bool
    {
        return $this !== self::UNLIMITED;
    }
}
```

### 4.4 File: `app/Modules/TicketHold/Enums/LinkStatusEnum.php`

```php
<?php

namespace App\Modules\TicketHold\Enums;

enum LinkStatusEnum: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
    case EXHAUSTED = 'exhausted';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::REVOKED => 'Revoked',
            self::EXHAUSTED => 'Fully Used',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::EXPIRED => 'gray',
            self::REVOKED => 'red',
            self::EXHAUSTED => 'blue',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }
}
```

---

## 5. Eloquent Models

### 5.1 File: `app/Modules/TicketHold/Models/TicketHold.php`

```php
<?php

namespace App\Modules\TicketHold\Models;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TicketHold extends Model
{
    use HasFactory, SoftDeletes;

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

    public function scopeActive($query)
    {
        return $query->where('status', HoldStatusEnum::ACTIVE);
    }

    public function scopeForOccurrence($query, int $occurrenceId)
    {
        return $query->where('event_occurrence_id', $occurrenceId);
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('organizer_id', $organizerId);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    // Accessors

    public function getTotalAllocatedAttribute(): int
    {
        return $this->allocations->sum('allocated_quantity');
    }

    public function getTotalPurchasedAttribute(): int
    {
        return $this->allocations->sum('purchased_quantity');
    }

    public function getTotalRemainingAttribute(): int
    {
        return $this->total_allocated - $this->total_purchased;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsUsableAttribute(): bool
    {
        return $this->status->isUsable() && !$this->is_expired;
    }

    // Methods

    public function release(User $releasedBy): void
    {
        $this->update([
            'status' => HoldStatusEnum::RELEASED,
            'released_at' => now(),
            'released_by' => $releasedBy->id,
        ]);
    }

    public function markExhausted(): void
    {
        if ($this->total_remaining <= 0) {
            $this->update(['status' => HoldStatusEnum::EXHAUSTED]);
        }
    }

    public function checkAndUpdateExpiration(): void
    {
        if ($this->is_expired && $this->status === HoldStatusEnum::ACTIVE) {
            $this->update(['status' => HoldStatusEnum::EXPIRED]);
        }
    }
}
```

### 5.2 File: `app/Modules/TicketHold/Models/HoldTicketAllocation.php`

```php
<?php

namespace App\Modules\TicketHold\Models;

use App\Models\TicketDefinition;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldTicketAllocation extends Model
{
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

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->allocated_quantity - $this->purchased_quantity);
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->remaining_quantity > 0;
    }

    // Methods

    /**
     * Calculate the effective price for this allocation.
     *
     * @param int|null $originalPrice Override original price (for pivot price_override)
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
     * Increment purchased quantity.
     */
    public function recordPurchase(int $quantity): void
    {
        $this->increment('purchased_quantity', $quantity);
        $this->ticketHold->markExhausted();
    }
}
```

### 5.3 File: `app/Modules/TicketHold/Models/PurchaseLink.php`

```php
<?php

namespace App\Modules\TicketHold\Models;

use App\Models\User;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseLink extends Model
{
    use HasFactory, SoftDeletes;

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

    public function scopeActive($query)
    {
        return $query->where('status', LinkStatusEnum::ACTIVE);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeAnonymous($query)
    {
        return $query->whereNull('assigned_user_id');
    }

    // Accessors

    public function getIsAnonymousAttribute(): bool
    {
        return is_null($this->assigned_user_id);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getRemainingQuantityAttribute(): ?int
    {
        if ($this->quantity_mode === QuantityModeEnum::UNLIMITED) {
            return null; // Unlimited
        }
        return max(0, $this->quantity_limit - $this->quantity_purchased);
    }

    public function getIsUsableAttribute(): bool
    {
        if (!$this->status->isUsable()) {
            return false;
        }
        if ($this->is_expired) {
            return false;
        }
        if (!$this->ticketHold->is_usable) {
            return false;
        }
        if ($this->remaining_quantity === 0) {
            return false;
        }
        return true;
    }

    public function getFullUrlAttribute(): string
    {
        return route('purchase-link.show', ['code' => $this->code]);
    }

    // Methods

    public static function generateUniqueCode(): string
    {
        do {
            $code = Str::random(16);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function canBeUsedByUser(?User $user): bool
    {
        // Anonymous link - anyone can use
        if ($this->is_anonymous) {
            return true;
        }

        // User-tied link - must match assigned user
        return $user && $user->id === $this->assigned_user_id;
    }

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

    public function revoke(User $revokedBy): void
    {
        $this->update([
            'status' => LinkStatusEnum::REVOKED,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy->id,
        ]);
    }

    public function recordPurchase(int $quantity): void
    {
        $this->increment('quantity_purchased', $quantity);

        // Check if exhausted
        if ($this->quantity_mode !== QuantityModeEnum::UNLIMITED) {
            $this->refresh();
            if ($this->remaining_quantity <= 0) {
                $this->update(['status' => LinkStatusEnum::EXHAUSTED]);
            }
        }
    }

    public function checkAndUpdateExpiration(): void
    {
        if ($this->is_expired && $this->status === LinkStatusEnum::ACTIVE) {
            $this->update(['status' => LinkStatusEnum::EXPIRED]);
        }
    }
}
```

### 5.4 File: `app/Modules/TicketHold/Models/PurchaseLinkAccess.php`

```php
<?php

namespace App\Modules\TicketHold\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseLinkAccess extends Model
{
    protected $table = 'purchase_link_accesses';

    protected $fillable = [
        'purchase_link_id',
        'user_id',
        'ip_address',
        'user_agent',
        'referer',
        'session_id',
        'resulted_in_purchase',
        'accessed_at',
    ];

    protected $casts = [
        'resulted_in_purchase' => 'boolean',
        'accessed_at' => 'datetime',
    ];

    // Relationships

    public function purchaseLink(): BelongsTo
    {
        return $this->belongsTo(PurchaseLink::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): HasOne
    {
        return $this->hasOne(PurchaseLinkPurchase::class, 'access_id');
    }

    // Methods

    public function markAsPurchased(): void
    {
        $this->update(['resulted_in_purchase' => true]);
    }
}
```

### 5.5 File: `app/Modules/TicketHold/Models/PurchaseLinkPurchase.php`

```php
<?php

namespace App\Modules\TicketHold\Models;

use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLinkPurchase extends Model
{
    protected $fillable = [
        'purchase_link_id',
        'booking_id',
        'transaction_id',
        'user_id',
        'quantity',
        'unit_price',
        'original_price',
        'currency',
        'access_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'original_price' => 'integer',
    ];

    // Relationships

    public function purchaseLink(): BelongsTo
    {
        return $this->belongsTo(PurchaseLink::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(PurchaseLinkAccess::class, 'access_id');
    }

    // Accessors

    public function getSavingsAttribute(): int
    {
        return max(0, ($this->original_price - $this->unit_price) * $this->quantity);
    }

    public function getTotalPaidAttribute(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
```

---

## 6. Data Transfer Objects (DTOs)

### 6.1 File: `app/Modules/TicketHold/DataTransferObjects/TicketHoldData.php`

```php
<?php

namespace App\Modules\TicketHold\DataTransferObjects;

use App\Modules\TicketHold\Enums\HoldStatusEnum;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class TicketHoldData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('event_occurrences', 'id')]
        public int $event_occurrence_id,

        #[Nullable]
        #[Exists('organizers', 'id')]
        public ?int $organizer_id,

        #[Required]
        #[Min(1)]
        #[Max(255)]
        public string $name,

        #[Nullable]
        #[Max(5000)]
        public ?string $description,

        #[Nullable]
        #[Max(5000)]
        public ?string $internal_notes,

        /** @var array<TicketAllocationData> */
        #[Required]
        public array $allocations,

        #[Nullable]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $expires_at,

        public int|Optional $id = new Optional(),
        public HoldStatusEnum|Optional $status = new Optional(),
    ) {}

    public static function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.ticket_definition_id' => ['required', 'exists:ticket_definitions,id'],
            'allocations.*.allocated_quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

### 6.2 File: `app/Modules/TicketHold/DataTransferObjects/TicketAllocationData.php`

```php
<?php

namespace App\Modules\TicketHold\DataTransferObjects;

use App\Modules\TicketHold\Enums\PricingModeEnum;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Data;

class TicketAllocationData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('ticket_definitions', 'id')]
        public int $ticket_definition_id,

        #[Required]
        #[Min(1)]
        public int $allocated_quantity,

        #[Required]
        public PricingModeEnum $pricing_mode,

        #[Nullable]
        #[Min(0)]
        public ?int $custom_price, // In cents

        #[Nullable]
        #[Min(0)]
        #[Max(100)]
        public ?int $discount_percentage,
    ) {}

    public static function rules(): array
    {
        return [
            'custom_price' => ['required_if:pricing_mode,fixed', 'nullable', 'integer', 'min:0'],
            'discount_percentage' => ['required_if:pricing_mode,percentage_discount', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
```

### 6.3 File: `app/Modules/TicketHold/DataTransferObjects/PurchaseLinkData.php`

```php
<?php

namespace App\Modules\TicketHold\DataTransferObjects;

use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PurchaseLinkData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('ticket_holds', 'id')]
        public int $ticket_hold_id,

        #[Nullable]
        #[Max(255)]
        public ?string $name,

        #[Nullable]
        #[Exists('users', 'id')]
        public ?int $assigned_user_id,

        #[Required]
        public QuantityModeEnum $quantity_mode,

        #[Nullable]
        #[Min(1)]
        public ?int $quantity_limit,

        #[Nullable]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $expires_at,

        #[Nullable]
        #[Max(5000)]
        public ?string $notes,

        #[Nullable]
        public ?array $metadata,

        public int|Optional $id = new Optional(),
        public string|Optional $code = new Optional(),
        public LinkStatusEnum|Optional $status = new Optional(),
    ) {}

    public static function rules(): array
    {
        return [
            'quantity_limit' => ['required_unless:quantity_mode,unlimited', 'nullable', 'integer', 'min:1'],
        ];
    }
}
```

### 6.4 File: `app/Modules/TicketHold/DataTransferObjects/HoldPurchaseRequestData.php`

```php
<?php

namespace App\Modules\TicketHold\DataTransferObjects;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class HoldPurchaseRequestData extends Data
{
    public function __construct(
        #[Required]
        public string $link_code,

        /** @var array<HoldPurchaseItemData> */
        #[Required]
        public array $items,
    ) {}

    public static function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_definition_id' => ['required', 'exists:ticket_definitions,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

### 6.5 File: `app/Modules/TicketHold/DataTransferObjects/HoldPurchaseItemData.php`

```php
<?php

namespace App\Modules\TicketHold\DataTransferObjects;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class HoldPurchaseItemData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('ticket_definitions', 'id')]
        public int $ticket_definition_id,

        #[Required]
        #[Min(1)]
        public int $quantity,
    ) {}
}
```

---

## 7. Service & Action Layer

### 7.1 Actions Overview

```
app/Modules/TicketHold/Actions/
├── CreateTicketHoldAction.php
├── UpdateTicketHoldAction.php
├── ReleaseTicketHoldAction.php
├── CreatePurchaseLinkAction.php
├── UpdatePurchaseLinkAction.php
├── RevokePurchaseLinkAction.php
├── RecordLinkAccessAction.php
├── ProcessHoldPurchaseAction.php
├── ValidateHoldAvailabilityAction.php
├── CalculateHoldPriceAction.php
└── ExpireHoldsAction.php (Scheduled)
```

### 7.2 File: `app/Modules/TicketHold/Actions/CreateTicketHoldAction.php`

```php
<?php

namespace App\Modules\TicketHold\Actions;

use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\DataTransferObjects\TicketHoldData;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Support\Facades\DB;

class CreateTicketHoldAction
{
    public function __construct(
        private ValidateHoldAvailabilityAction $validateAvailability
    ) {}

    /**
     * @throws InsufficientInventoryException
     */
    public function execute(TicketHoldData $data, User $creator): TicketHold
    {
        return DB::transaction(function () use ($data, $creator) {
            // Validate inventory availability for all allocations
            foreach ($data->allocations as $allocation) {
                $this->validateAvailability->execute(
                    $allocation->ticket_definition_id,
                    $allocation->allocated_quantity,
                    $data->event_occurrence_id
                );
            }

            // Create the hold
            $hold = TicketHold::create([
                'event_occurrence_id' => $data->event_occurrence_id,
                'organizer_id' => $data->organizer_id,
                'created_by' => $creator->id,
                'name' => $data->name,
                'description' => $data->description,
                'internal_notes' => $data->internal_notes,
                'status' => HoldStatusEnum::ACTIVE,
                'expires_at' => $data->expires_at,
            ]);

            // Create allocations
            foreach ($data->allocations as $allocationData) {
                HoldTicketAllocation::create([
                    'ticket_hold_id' => $hold->id,
                    'ticket_definition_id' => $allocationData->ticket_definition_id,
                    'allocated_quantity' => $allocationData->allocated_quantity,
                    'purchased_quantity' => 0,
                    'pricing_mode' => $allocationData->pricing_mode,
                    'custom_price' => $allocationData->custom_price,
                    'discount_percentage' => $allocationData->discount_percentage,
                ]);
            }

            return $hold->load('allocations.ticketDefinition', 'eventOccurrence.event');
        });
    }
}
```

### 7.3 File: `app/Modules/TicketHold/Actions/ValidateHoldAvailabilityAction.php`

```php
<?php

namespace App\Modules\TicketHold\Actions;

use App\Models\Booking;
use App\Models\TicketDefinition;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use Illuminate\Support\Facades\DB;

class ValidateHoldAvailabilityAction
{
    /**
     * Validate that sufficient inventory exists for a new hold allocation.
     *
     * @throws InsufficientInventoryException
     */
    public function execute(
        int $ticketDefinitionId,
        int $requestedQuantity,
        int $eventOccurrenceId,
        ?int $excludeHoldId = null
    ): void {
        $ticketDefinition = TicketDefinition::findOrFail($ticketDefinitionId);

        // If unlimited inventory, always available
        if (is_null($ticketDefinition->total_quantity)) {
            return;
        }

        // Get total inventory
        $totalInventory = $ticketDefinition->total_quantity;

        // Get booked quantity (confirmed bookings)
        $bookedQuantity = Booking::where('ticket_definition_id', $ticketDefinitionId)
            ->whereIn('status', ['confirmed', 'pending_confirmation'])
            ->sum('quantity');

        // Get held quantity (excluding current hold if updating)
        $heldQuery = HoldTicketAllocation::where('ticket_definition_id', $ticketDefinitionId)
            ->whereHas('ticketHold', function ($q) use ($eventOccurrenceId) {
                $q->where('event_occurrence_id', $eventOccurrenceId)
                  ->active();
            });

        if ($excludeHoldId) {
            $heldQuery->where('ticket_hold_id', '!=', $excludeHoldId);
        }

        $heldQuantity = $heldQuery->sum(DB::raw('allocated_quantity - purchased_quantity'));

        // Calculate available
        $available = $totalInventory - $bookedQuantity - $heldQuantity;

        if ($requestedQuantity > $available) {
            throw new InsufficientInventoryException(
                "Insufficient inventory for ticket '{$ticketDefinition->name}'. " .
                "Requested: {$requestedQuantity}, Available: {$available}"
            );
        }
    }
}
```

### 7.4 File: `app/Modules/TicketHold/Actions/CreatePurchaseLinkAction.php`

```php
<?php

namespace App\Modules\TicketHold\Actions;

use App\Modules\TicketHold\DataTransferObjects\PurchaseLinkData;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;

class CreatePurchaseLinkAction
{
    /**
     * @throws HoldNotActiveException
     */
    public function execute(PurchaseLinkData $data): PurchaseLink
    {
        $hold = TicketHold::findOrFail($data->ticket_hold_id);

        if (!$hold->is_usable) {
            throw new HoldNotActiveException(
                "Cannot create link for hold '{$hold->name}'. Hold is not active."
            );
        }

        return PurchaseLink::create([
            'ticket_hold_id' => $data->ticket_hold_id,
            'name' => $data->name,
            'assigned_user_id' => $data->assigned_user_id,
            'quantity_mode' => $data->quantity_mode,
            'quantity_limit' => $data->quantity_limit,
            'quantity_purchased' => 0,
            'status' => LinkStatusEnum::ACTIVE,
            'expires_at' => $data->expires_at,
            'notes' => $data->notes,
            'metadata' => $data->metadata,
        ]);
    }
}
```

### 7.5 File: `app/Modules/TicketHold/Actions/ProcessHoldPurchaseAction.php`

```php
<?php

namespace App\Modules\TicketHold\Actions;

use App\Enums\BookingStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Helpers\QrCodeHelper;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\TicketHold\DataTransferObjects\HoldPurchaseRequestData;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Exceptions\InsufficientHoldInventoryException;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Exceptions\UserNotAuthorizedForLinkException;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;

class ProcessHoldPurchaseAction
{
    public function __construct(
        private CalculateHoldPriceAction $calculatePrice
    ) {}

    /**
     * Process a purchase through a hold link.
     *
     * @return array{requires_payment: bool, checkout_url?: string, booking_id?: int, transaction_id?: int}
     * @throws LinkNotUsableException
     * @throws UserNotAuthorizedForLinkException
     * @throws HoldNotActiveException
     * @throws InsufficientHoldInventoryException
     */
    public function execute(
        HoldPurchaseRequestData $data,
        User $user,
        ?PurchaseLinkAccess $access = null
    ): array {
        return DB::transaction(function () use ($data, $user, $access) {
            $link = PurchaseLink::with(['ticketHold.allocations.ticketDefinition', 'ticketHold.eventOccurrence.event'])
                ->byCode($data->link_code)
                ->firstOrFail();

            // Validate link is usable
            $this->validateLink($link, $user, $data);

            $hold = $link->ticketHold;
            $occurrence = $hold->eventOccurrence;

            $totalAmount = 0;
            $currency = null;
            $lineItemsForStripe = [];
            $purchaseRecords = [];
            $totalQuantity = 0;

            foreach ($data->items as $itemData) {
                $allocation = $hold->allocations
                    ->where('ticket_definition_id', $itemData->ticket_definition_id)
                    ->first();

                if (!$allocation) {
                    throw new InsufficientHoldInventoryException(
                        "Ticket not available in this hold allocation."
                    );
                }

                if ($itemData->quantity > $allocation->remaining_quantity) {
                    throw new InsufficientHoldInventoryException(
                        "Not enough tickets available. Requested: {$itemData->quantity}, Available: {$allocation->remaining_quantity}"
                    );
                }

                $ticketDefinition = $allocation->ticketDefinition;
                $effectivePrice = $allocation->calculateEffectivePrice();
                $originalPrice = $ticketDefinition->price;
                $itemTotal = $effectivePrice * $itemData->quantity;

                $totalAmount += $itemTotal;
                $totalQuantity += $itemData->quantity;
                $currency = $currency ?? strtolower($ticketDefinition->currency);

                $purchaseRecords[] = [
                    'allocation' => $allocation,
                    'ticket_definition' => $ticketDefinition,
                    'quantity' => $itemData->quantity,
                    'unit_price' => $effectivePrice,
                    'original_price' => $originalPrice,
                ];

                if ($effectivePrice > 0) {
                    $lineItemsForStripe[] = [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $effectivePrice,
                            'product_data' => [
                                'name' => $ticketDefinition->name,
                                'description' => $ticketDefinition->description ?: 'Event Ticket (Reserved)',
                            ],
                        ],
                        'quantity' => $itemData->quantity,
                    ];
                }
            }

            // Validate total quantity against link limit
            if (!$link->canPurchaseQuantity($totalQuantity)) {
                throw new LinkNotUsableException(
                    "Cannot purchase {$totalQuantity} tickets. Link limit exceeded."
                );
            }

            $currency = $currency ?? strtolower(config('cashier.currency'));
            $transactionStatus = ($totalAmount > 0) ? TransactionStatusEnum::PENDING_PAYMENT : TransactionStatusEnum::CONFIRMED;
            $bookingStatus = ($transactionStatus === TransactionStatusEnum::CONFIRMED)
                ? BookingStatusEnum::CONFIRMED
                : BookingStatusEnum::PENDING_CONFIRMATION;

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'status' => $transactionStatus,
                'metadata' => [
                    'purchase_link_id' => $link->id,
                    'purchase_link_code' => $link->code,
                    'ticket_hold_id' => $hold->id,
                ],
            ]);

            // Create bookings and purchase records
            $createdBookings = [];
            foreach ($purchaseRecords as $record) {
                for ($i = 0; $i < $record['quantity']; $i++) {
                    $qrCode = QrCodeHelper::generate();
                    $booking = Booking::create([
                        'transaction_id' => $transaction->id,
                        'event_id' => $occurrence->event->id,
                        'ticket_definition_id' => $record['ticket_definition']->id,
                        'booking_number' => $qrCode,
                        'qr_code_identifier' => $qrCode,
                        'quantity' => 1,
                        'price_at_booking' => $record['unit_price'],
                        'currency_at_booking' => $currency,
                        'status' => $bookingStatus,
                        'max_allowed_check_ins' => $record['ticket_definition']->max_check_ins ?? 1,
                        'metadata' => [
                            'purchase_link_id' => $link->id,
                            'original_price' => $record['original_price'],
                            'hold_discount_applied' => $record['unit_price'] < $record['original_price'],
                        ],
                    ]);
                    $createdBookings[] = $booking;
                }

                // Record purchase for analytics
                PurchaseLinkPurchase::create([
                    'purchase_link_id' => $link->id,
                    'booking_id' => $createdBookings[count($createdBookings) - 1]->id,
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'quantity' => $record['quantity'],
                    'unit_price' => $record['unit_price'],
                    'original_price' => $record['original_price'],
                    'currency' => $currency,
                    'access_id' => $access?->id,
                ]);

                // Update allocation purchased count
                $record['allocation']->recordPurchase($record['quantity']);
            }

            // Update link purchased count
            $link->recordPurchase($totalQuantity);

            // Mark access as resulted in purchase
            if ($access) {
                $access->markAsPurchased();
            }

            // Free booking - return success
            if ($totalAmount <= 0) {
                return [
                    'requires_payment' => false,
                    'booking_confirmed' => true,
                    'booking_id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                ];
            }

            // Paid booking - create Stripe checkout
            try {
                $checkoutSession = $user->checkout($lineItemsForStripe, [
                    'allow_promotion_codes' => true,
                    'success_url' => route('payment.success') . '?transaction_id=' . $transaction->id . '&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('payment.cancel') . '?transaction_id=' . $transaction->id . '&session_id={CHECKOUT_SESSION_ID}',
                    'metadata' => [
                        'transaction_id' => $transaction->id,
                        'purchase_link_id' => $link->id,
                    ],
                ]);

                $transaction->update([
                    'payment_gateway' => 'stripe',
                    'payment_gateway_transaction_id' => $checkoutSession->id,
                ]);

                return [
                    'requires_payment' => true,
                    'checkout_url' => $checkoutSession->url,
                    'booking_id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                ];
            } catch (ApiErrorException $e) {
                throw $e;
            }
        });
    }

    private function validateLink(PurchaseLink $link, User $user, HoldPurchaseRequestData $data): void
    {
        if (!$link->is_usable) {
            throw new LinkNotUsableException(
                "This purchase link is no longer active. Status: {$link->status->label()}"
            );
        }

        if (!$link->canBeUsedByUser($user)) {
            throw new UserNotAuthorizedForLinkException(
                "You are not authorized to use this purchase link."
            );
        }

        if (!$link->ticketHold->is_usable) {
            throw new HoldNotActiveException(
                "The ticket hold associated with this link is no longer active."
            );
        }
    }
}
```

### 7.6 File: `app/Modules/TicketHold/Actions/RecordLinkAccessAction.php`

```php
<?php

namespace App\Modules\TicketHold\Actions;

use App\Models\User;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use Illuminate\Http\Request;

class RecordLinkAccessAction
{
    public function execute(PurchaseLink $link, Request $request, ?User $user = null): PurchaseLinkAccess
    {
        return PurchaseLinkAccess::create([
            'purchase_link_id' => $link->id,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('Referer'),
            'session_id' => session()->getId(),
            'resulted_in_purchase' => false,
            'accessed_at' => now(),
        ]);
    }
}
```

### 7.7 File: `app/Modules/TicketHold/Services/TicketHoldService.php`

```php
<?php

namespace App\Modules\TicketHold\Services;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\TicketHold\DataTransferObjects\PurchaseLinkData;
use App\Modules\TicketHold\DataTransferObjects\TicketHoldData;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TicketHoldService
{
    /**
     * Get paginated holds with filters.
     */
    public function getPaginatedHolds(array $filters = []): LengthAwarePaginator
    {
        $query = TicketHold::with([
            'allocations.ticketDefinition',
            'eventOccurrence.event',
            'creator',
            'purchaseLinks',
        ]);

        if (!empty($filters['event_occurrence_id'])) {
            $query->forOccurrence($filters['event_occurrence_id']);
        }

        if (!empty($filters['organizer_id'])) {
            $query->forOrganizer($filters['organizer_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    /**
     * Get holds for organizer's events.
     */
    public function getHoldsForOrganizer(User $user, array $filters = []): LengthAwarePaginator
    {
        // Get organizer IDs user belongs to
        $organizerIds = Organizer::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->pluck('id');

        $filters['organizer_ids'] = $organizerIds->toArray();

        $query = TicketHold::with([
            'allocations.ticketDefinition',
            'eventOccurrence.event',
            'creator',
            'purchaseLinks',
        ])->whereIn('organizer_id', $organizerIds);

        // Apply other filters...
        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    /**
     * Get available ticket definitions for a hold (those with remaining inventory).
     */
    public function getAvailableTicketsForOccurrence(int $occurrenceId): Collection
    {
        $occurrence = EventOccurrence::with('ticketDefinitions')->findOrFail($occurrenceId);

        return $occurrence->ticketDefinitions->map(function ($ticket) use ($occurrenceId) {
            $availableQuantity = $this->calculateAvailableForHold($ticket->id, $occurrenceId);

            return [
                'id' => $ticket->id,
                'name' => $ticket->name,
                'price' => $ticket->price,
                'currency' => $ticket->currency,
                'total_quantity' => $ticket->total_quantity,
                'available_for_hold' => $availableQuantity,
                'pivot' => $ticket->pivot,
            ];
        });
    }

    /**
     * Calculate available quantity for new hold allocation.
     */
    public function calculateAvailableForHold(int $ticketDefinitionId, int $occurrenceId): int
    {
        // Implementation similar to ValidateHoldAvailabilityAction
        // Returns max quantity that can be allocated to a new hold
        return 0; // Placeholder
    }

    /**
     * Get hold statistics for dashboard.
     */
    public function getHoldStatistics(?int $organizerId = null): array
    {
        $baseQuery = TicketHold::query();

        if ($organizerId) {
            $baseQuery->forOrganizer($organizerId);
        }

        return [
            'total_holds' => (clone $baseQuery)->count(),
            'active_holds' => (clone $baseQuery)->active()->count(),
            'total_allocated' => (clone $baseQuery)->active()
                ->withSum('allocations', 'allocated_quantity')
                ->get()
                ->sum('allocations_sum_allocated_quantity'),
            'total_purchased' => (clone $baseQuery)
                ->withSum('allocations', 'purchased_quantity')
                ->get()
                ->sum('allocations_sum_purchased_quantity'),
            'total_links' => PurchaseLink::whereHas('ticketHold', function ($q) use ($organizerId) {
                if ($organizerId) {
                    $q->forOrganizer($organizerId);
                }
            })->count(),
            'active_links' => PurchaseLink::active()->whereHas('ticketHold', function ($q) use ($organizerId) {
                if ($organizerId) {
                    $q->forOrganizer($organizerId);
                }
            })->count(),
        ];
    }
}
```

---

## 8. API Endpoints

### 8.1 Route Definitions

**File: `routes/web.php` (additions)**

```php
// --- ADMIN: TICKET HOLDS ---
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth'])
    ->group(function () {
        // Ticket Holds (shared admin routes)
        Route::resource('ticket-holds', \App\Modules\TicketHold\Controllers\Admin\TicketHoldController::class);
        Route::post('ticket-holds/{ticketHold}/release', [\App\Modules\TicketHold\Controllers\Admin\TicketHoldController::class, 'release'])
            ->name('ticket-holds.release');

        // Purchase Links
        Route::resource('ticket-holds.purchase-links', \App\Modules\TicketHold\Controllers\Admin\PurchaseLinkController::class)
            ->shallow();
        Route::post('purchase-links/{purchaseLink}/revoke', [\App\Modules\TicketHold\Controllers\Admin\PurchaseLinkController::class, 'revoke'])
            ->name('purchase-links.revoke');

        // Analytics
        Route::get('ticket-holds/{ticketHold}/analytics', [\App\Modules\TicketHold\Controllers\Admin\HoldAnalyticsController::class, 'show'])
            ->name('ticket-holds.analytics');
        Route::get('purchase-links/{purchaseLink}/analytics', [\App\Modules\TicketHold\Controllers\Admin\LinkAnalyticsController::class, 'show'])
            ->name('purchase-links.analytics');

        // API endpoints for frontend
        Route::prefix('api/ticket-holds')->name('api.ticket-holds.')->group(function () {
            Route::get('available-tickets/{occurrence}', [\App\Modules\TicketHold\Controllers\Admin\TicketHoldController::class, 'availableTickets'])
                ->name('available-tickets');
            Route::post('search-users', [\App\Modules\TicketHold\Controllers\Admin\PurchaseLinkController::class, 'searchUsers'])
                ->name('search-users');
        });
    });

// --- PUBLIC: PURCHASE LINKS ---
Route::prefix('reserve')->name('purchase-link.')->group(function () {
    // Public link access (no auth required to view)
    Route::get('/{code}', [\App\Modules\TicketHold\Controllers\Public\PurchaseLinkController::class, 'show'])
        ->name('show');

    // Purchase requires auth
    Route::middleware(['auth'])->group(function () {
        Route::post('/{code}/purchase', [\App\Modules\TicketHold\Controllers\Public\PurchaseLinkController::class, 'purchase'])
            ->name('purchase');
    });
});
```

### 8.2 Endpoint Summary Table

| Method | Endpoint | Controller | Purpose |
|--------|----------|------------|---------|
| GET | `/admin/ticket-holds` | `TicketHoldController@index` | List holds |
| GET | `/admin/ticket-holds/create` | `TicketHoldController@create` | Create form |
| POST | `/admin/ticket-holds` | `TicketHoldController@store` | Store new hold |
| GET | `/admin/ticket-holds/{id}` | `TicketHoldController@show` | View hold |
| GET | `/admin/ticket-holds/{id}/edit` | `TicketHoldController@edit` | Edit form |
| PUT | `/admin/ticket-holds/{id}` | `TicketHoldController@update` | Update hold |
| DELETE | `/admin/ticket-holds/{id}` | `TicketHoldController@destroy` | Delete hold |
| POST | `/admin/ticket-holds/{id}/release` | `TicketHoldController@release` | Release hold |
| GET | `/admin/ticket-holds/{id}/purchase-links` | `PurchaseLinkController@index` | List links |
| POST | `/admin/ticket-holds/{id}/purchase-links` | `PurchaseLinkController@store` | Create link |
| GET | `/admin/purchase-links/{id}` | `PurchaseLinkController@show` | View link |
| PUT | `/admin/purchase-links/{id}` | `PurchaseLinkController@update` | Update link |
| DELETE | `/admin/purchase-links/{id}` | `PurchaseLinkController@destroy` | Delete link |
| POST | `/admin/purchase-links/{id}/revoke` | `PurchaseLinkController@revoke` | Revoke link |
| GET | `/reserve/{code}` | `PublicPurchaseLinkController@show` | Public link page |
| POST | `/reserve/{code}/purchase` | `PublicPurchaseLinkController@purchase` | Process purchase |

---

## 9. Authorization & Policies

### 9.1 File: `app/Modules/TicketHold/Policies/TicketHoldPolicy.php`

```php
<?php

namespace App\Modules\TicketHold\Policies;

use App\Enums\RoleNameEnum;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketHoldPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view any holds.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all
        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            return true;
        }

        // Organizer members can view (filtered in controller)
        return $this->userBelongsToAnyOrganizer($user);
    }

    /**
     * Determine if user can view the hold.
     */
    public function view(User $user, TicketHold $hold): bool
    {
        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            return true;
        }

        return $this->userCanManageHold($user, $hold);
    }

    /**
     * Determine if user can create holds.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            return true;
        }

        return $this->userBelongsToAnyOrganizer($user);
    }

    /**
     * Determine if user can update the hold.
     */
    public function update(User $user, TicketHold $hold): bool
    {
        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            return true;
        }

        return $this->userCanManageHold($user, $hold);
    }

    /**
     * Determine if user can delete the hold.
     */
    public function delete(User $user, TicketHold $hold): bool
    {
        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            return true;
        }

        return $this->userCanManageHold($user, $hold);
    }

    /**
     * Determine if user can release the hold.
     */
    public function release(User $user, TicketHold $hold): bool
    {
        return $this->update($user, $hold);
    }

    /**
     * Check if user belongs to any organizer.
     */
    private function userBelongsToAnyOrganizer(User $user): bool
    {
        return Organizer::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->exists();
    }

    /**
     * Check if user can manage this specific hold.
     */
    private function userCanManageHold(User $user, TicketHold $hold): bool
    {
        if (!$hold->organizer_id) {
            return false;
        }

        return Organizer::where('id', $hold->organizer_id)
            ->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->exists();
    }
}
```

### 9.2 File: `app/Modules/TicketHold/Policies/PurchaseLinkPolicy.php`

```php
<?php

namespace App\Modules\TicketHold\Policies;

use App\Enums\RoleNameEnum;
use App\Models\User;
use App\Modules\TicketHold\Models\PurchaseLink;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseLinkPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view any links.
     */
    public function viewAny(User $user): bool
    {
        return app(TicketHoldPolicy::class)->viewAny($user);
    }

    /**
     * Determine if user can view the link.
     */
    public function view(User $user, PurchaseLink $link): bool
    {
        return app(TicketHoldPolicy::class)->view($user, $link->ticketHold);
    }

    /**
     * Determine if user can create links for a hold.
     */
    public function create(User $user): bool
    {
        return app(TicketHoldPolicy::class)->create($user);
    }

    /**
     * Determine if user can update the link.
     */
    public function update(User $user, PurchaseLink $link): bool
    {
        return app(TicketHoldPolicy::class)->update($user, $link->ticketHold);
    }

    /**
     * Determine if user can delete the link.
     */
    public function delete(User $user, PurchaseLink $link): bool
    {
        return app(TicketHoldPolicy::class)->delete($user, $link->ticketHold);
    }

    /**
     * Determine if user can revoke the link.
     */
    public function revoke(User $user, PurchaseLink $link): bool
    {
        return $this->update($user, $link);
    }
}
```

### 9.3 Register Policies

**File: `app/Providers/AuthServiceProvider.php` (additions)**

```php
use App\Modules\TicketHold\Models\TicketHold;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Policies\TicketHoldPolicy;
use App\Modules\TicketHold\Policies\PurchaseLinkPolicy;

protected $policies = [
    // ... existing policies
    TicketHold::class => TicketHoldPolicy::class,
    PurchaseLink::class => PurchaseLinkPolicy::class,
];
```

---

## 10. Frontend Components

### 10.1 Directory Structure

```
resources/js/Pages/Admin/TicketHolds/
├── Index.vue              # List all holds with filters
├── Create.vue             # Create new hold form
├── Edit.vue               # Edit existing hold
├── Show.vue               # View hold details with links
└── components/
    ├── HoldForm.vue       # Shared form component
    ├── AllocationTable.vue # Ticket allocation editor
    ├── HoldStatusBadge.vue # Status indicator
    └── HoldStats.vue      # Statistics summary

resources/js/Pages/Admin/PurchaseLinks/
├── Index.vue              # List links for a hold
├── Create.vue             # Create new link
├── Edit.vue               # Edit existing link
├── Show.vue               # View link with analytics
└── components/
    ├── LinkForm.vue       # Shared form component
    ├── LinkUrlDisplay.vue # Copyable URL display
    ├── LinkStatusBadge.vue
    ├── UsageChart.vue     # Analytics chart
    └── AccessLog.vue      # Access history table

resources/js/Pages/Public/PurchaseLink/
├── Show.vue               # Public link landing page
└── components/
    ├── HoldTicketSelector.vue  # Ticket selection
    ├── HoldPriceDisplay.vue    # Special pricing display
    └── HoldCheckoutButton.vue  # Purchase button
```

### 10.2 Key Component Specifications

**Admin: HoldForm.vue**
- Event occurrence selector (with ticket availability preview)
- Dynamic ticket allocation rows (add/remove tickets)
- Pricing mode selector per ticket (original/fixed/discount/free)
- Expiration date picker
- Internal notes field

**Admin: LinkForm.vue**
- Name field (optional)
- User assignment toggle (anonymous vs user-tied)
- User search autocomplete (when user-tied)
- Quantity mode selector (fixed/maximum/unlimited)
- Quantity limit input (conditional)
- Expiration date picker
- Notes field

**Public: Show.vue (Purchase Link Landing)**
- Event details header
- Available tickets from hold (with special pricing highlighted)
- Quantity selectors per ticket type
- Price summary with savings displayed
- Login prompt (if not authenticated)
- User mismatch warning (if link is user-tied)
- Checkout button

### 10.3 State Management

Use Inertia.js forms with reactive state:

```typescript
// types/ticket-hold.ts
export interface TicketHold {
  id: number;
  uuid: string;
  name: string;
  description: string | null;
  status: 'active' | 'expired' | 'released' | 'exhausted';
  expires_at: string | null;
  total_allocated: number;
  total_purchased: number;
  total_remaining: number;
  allocations: HoldTicketAllocation[];
  purchase_links: PurchaseLink[];
  event_occurrence: EventOccurrence;
}

export interface HoldTicketAllocation {
  id: number;
  ticket_definition_id: number;
  allocated_quantity: number;
  purchased_quantity: number;
  remaining_quantity: number;
  pricing_mode: 'original' | 'fixed' | 'percentage_discount' | 'free';
  custom_price: number | null;
  discount_percentage: number | null;
  ticket_definition: TicketDefinition;
}

export interface PurchaseLink {
  id: number;
  uuid: string;
  code: string;
  name: string | null;
  full_url: string;
  is_anonymous: boolean;
  assigned_user: User | null;
  quantity_mode: 'fixed' | 'maximum' | 'unlimited';
  quantity_limit: number | null;
  quantity_purchased: number;
  remaining_quantity: number | null;
  status: 'active' | 'expired' | 'revoked' | 'exhausted';
  expires_at: string | null;
}
```

---

## 11. Coupon Integration

### 11.1 Integration Strategy

Hold purchases should integrate with the existing Coupon module, allowing users to apply coupon codes on top of hold pricing.

**Flow:**
1. User accesses purchase link
2. User selects tickets at hold prices
3. User can optionally apply coupon code
4. Coupon discount applies to the (already discounted) hold price
5. Final price = hold_price - coupon_discount

### 11.2 Implementation Points

**Modify: `ProcessHoldPurchaseAction.php`**

```php
public function execute(
    HoldPurchaseRequestData $data,
    User $user,
    ?PurchaseLinkAccess $access = null,
    ?string $couponCode = null  // Add coupon support
): array {
    // ... existing validation

    // If coupon provided, validate and calculate discount
    $couponDiscount = 0;
    $appliedCoupon = null;

    if ($couponCode) {
        $couponService = app(CouponService::class);
        $appliedCoupon = $couponService->validateAndApply($couponCode, $user, $totalAmount);
        $couponDiscount = $appliedCoupon->discount_amount;
    }

    $finalAmount = max(0, $totalAmount - $couponDiscount);

    // ... continue with purchase
}
```

**Update: `HoldPurchaseRequestData.php`**

```php
public function __construct(
    public string $link_code,
    public array $items,
    #[Nullable]
    public ?string $coupon_code = null,  // Add optional coupon
) {}
```

### 11.3 Frontend Integration

Add coupon input field to the public purchase link page:

```vue
<!-- In PurchaseLink/Show.vue -->
<CouponInput
  v-model="couponCode"
  @apply="applyCoupon"
  @remove="removeCoupon"
  :applied-discount="appliedDiscount"
/>
```

---

## 12. Analytics & Reporting

### 12.1 Metrics to Track

**Hold-Level Metrics:**
- Total allocated tickets
- Total purchased tickets
- Remaining available tickets
- Utilization rate (purchased / allocated)
- Revenue from hold (total + by ticket type)
- Savings given (original - actual)

**Link-Level Metrics:**
- Total accesses (views)
- Unique visitors
- Conversion rate (purchases / accesses)
- Total tickets purchased through link
- Total revenue through link
- Time to first purchase
- Most recent activity

### 12.2 Analytics Service

**File: `app/Modules/TicketHold/Services/HoldAnalyticsService.php`**

```php
<?php

namespace App\Modules\TicketHold\Services;

use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Support\Carbon;

class HoldAnalyticsService
{
    public function getHoldAnalytics(TicketHold $hold): array
    {
        $allocations = $hold->allocations->load('ticketDefinition');

        $totalOriginalValue = 0;
        $totalActualRevenue = 0;

        foreach ($allocations as $allocation) {
            $originalPrice = $allocation->ticketDefinition->price;
            $effectivePrice = $allocation->calculateEffectivePrice();

            $totalOriginalValue += $originalPrice * $allocation->purchased_quantity;
            $totalActualRevenue += $effectivePrice * $allocation->purchased_quantity;
        }

        return [
            'total_allocated' => $hold->total_allocated,
            'total_purchased' => $hold->total_purchased,
            'total_remaining' => $hold->total_remaining,
            'utilization_rate' => $hold->total_allocated > 0
                ? round(($hold->total_purchased / $hold->total_allocated) * 100, 1)
                : 0,
            'total_revenue' => $totalActualRevenue,
            'total_savings_given' => $totalOriginalValue - $totalActualRevenue,
            'link_count' => $hold->purchaseLinks()->count(),
            'active_link_count' => $hold->purchaseLinks()->active()->count(),
            'allocation_breakdown' => $allocations->map(fn ($a) => [
                'ticket_name' => $a->ticketDefinition->name,
                'allocated' => $a->allocated_quantity,
                'purchased' => $a->purchased_quantity,
                'remaining' => $a->remaining_quantity,
                'pricing_mode' => $a->pricing_mode->label(),
            ]),
        ];
    }

    public function getLinkAnalytics(PurchaseLink $link, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $accessQuery = PurchaseLinkAccess::where('purchase_link_id', $link->id);
        $purchaseQuery = PurchaseLinkPurchase::where('purchase_link_id', $link->id);

        if ($startDate) {
            $accessQuery->where('accessed_at', '>=', $startDate);
            $purchaseQuery->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $accessQuery->where('accessed_at', '<=', $endDate);
            $purchaseQuery->where('created_at', '<=', $endDate);
        }

        $totalAccesses = (clone $accessQuery)->count();
        $uniqueVisitors = (clone $accessQuery)->distinct('ip_address')->count('ip_address');
        $purchasesCount = (clone $purchaseQuery)->count();
        $purchasesWithAccess = (clone $accessQuery)->where('resulted_in_purchase', true)->count();

        $conversionRate = $totalAccesses > 0
            ? round(($purchasesWithAccess / $totalAccesses) * 100, 1)
            : 0;

        $revenue = (clone $purchaseQuery)->selectRaw('SUM(unit_price * quantity) as total')->first()->total ?? 0;
        $savings = (clone $purchaseQuery)->selectRaw('SUM((original_price - unit_price) * quantity) as total')->first()->total ?? 0;

        $firstPurchase = (clone $purchaseQuery)->oldest()->first();
        $lastActivity = (clone $accessQuery)->latest('accessed_at')->first();

        return [
            'total_accesses' => $totalAccesses,
            'unique_visitors' => $uniqueVisitors,
            'total_purchases' => $purchasesCount,
            'conversion_rate' => $conversionRate,
            'total_revenue' => $revenue,
            'total_savings_given' => $savings,
            'tickets_purchased' => $link->quantity_purchased,
            'tickets_remaining' => $link->remaining_quantity,
            'first_purchase_at' => $firstPurchase?->created_at,
            'last_activity_at' => $lastActivity?->accessed_at,
            'daily_accesses' => $this->getDailyAccessCounts($link, $startDate, $endDate),
        ];
    }

    private function getDailyAccessCounts(PurchaseLink $link, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return PurchaseLinkAccess::where('purchase_link_id', $link->id)
            ->whereBetween('accessed_at', [$startDate, $endDate])
            ->selectRaw('DATE(accessed_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();
    }
}
```

### 12.3 Dashboard Widget

Add to admin dashboard to show:
- Active holds count
- Total held tickets
- Recent link activity
- Top performing links

---

## 13. Edge Cases & Error Handling

### 13.1 Edge Cases Matrix

| Scenario | Behavior | Implementation |
|----------|----------|----------------|
| Hold expires while user is on checkout | Block purchase, show expiration message | Check in `ProcessHoldPurchaseAction` |
| Link expires mid-session | Block purchase, show expiration message | Check `is_usable` before processing |
| User-tied link accessed by wrong user | Show unauthorized message, no ticket display | Check in controller before rendering |
| Event cancelled | Holds should be released, links invalidated | Event observer releases holds |
| Concurrent purchases exhaust hold | Race condition prevention with DB locks | Use `lockForUpdate()` in transaction |
| Partial purchase on fixed-quantity link | Reject - must purchase exact quantity | Validation in `canPurchaseQuantity()` |
| Hold released while links still active | Links become unusable (hold check fails) | `is_usable` checks parent hold |
| Ticket definition deleted | Soft delete, allocations remain for history | FK constraint + soft deletes |
| Refund on hold purchase | Decrement purchased_quantity, restore availability | Webhook handler or manual action |

### 13.2 Exception Classes

**File: `app/Modules/TicketHold/Exceptions/`**

```php
// HoldNotActiveException.php
class HoldNotActiveException extends \Exception {}

// LinkNotUsableException.php
class LinkNotUsableException extends \Exception {}

// UserNotAuthorizedForLinkException.php
class UserNotAuthorizedForLinkException extends \Exception {}

// InsufficientHoldInventoryException.php
class InsufficientHoldInventoryException extends \Exception {}

// InsufficientInventoryException.php
class InsufficientInventoryException extends \Exception {}

// HoldQuantityExceededException.php
class HoldQuantityExceededException extends \Exception {}
```

### 13.3 Concurrent Purchase Handling

```php
// In ProcessHoldPurchaseAction::execute()
return DB::transaction(function () use ($data, $user, $access) {
    // Lock the allocation rows to prevent race conditions
    $link = PurchaseLink::with(['ticketHold.allocations' => function ($q) {
        $q->lockForUpdate();
    }])->byCode($data->link_code)->firstOrFail();

    // Also lock the link itself
    $link->lockForUpdate();

    // ... proceed with validation and purchase
});
```

---

## 14. Security Considerations

### 14.1 Security Checklist

| Concern | Mitigation |
|---------|------------|
| Link enumeration | Use UUID + random 16-char code, rate limit |
| Unauthorized access | Policy checks on all admin routes |
| CSRF | Standard Laravel CSRF protection |
| XSS | Vue auto-escaping, sanitize notes |
| SQL Injection | Eloquent parameterized queries |
| Mass assignment | Explicit `$fillable` arrays |
| Rate limiting | Apply to public link access route |
| Session fixation | Regenerate session on login |

### 14.2 Rate Limiting

**File: `routes/web.php`**

```php
Route::prefix('reserve')->name('purchase-link.')->group(function () {
    Route::get('/{code}', [PurchaseLinkController::class, 'show'])
        ->name('show')
        ->middleware('throttle:60,1'); // 60 requests per minute

    Route::middleware(['auth', 'throttle:10,1'])->group(function () {
        Route::post('/{code}/purchase', [PurchaseLinkController::class, 'purchase'])
            ->name('purchase'); // 10 purchases per minute
    });
});
```

### 14.3 Audit Logging

Consider logging sensitive operations:
- Hold creation/modification/release
- Link creation/revocation
- Purchase completions
- Access attempts (already tracked for analytics)

---

## 15. Implementation Phases

### Phase 1: Core Infrastructure (Week 1)
**Dependencies:** None

**Deliverables:**
1. Database migrations
2. Eloquent models with relationships
3. Enums
4. Basic DTOs
5. Unit tests for models

**Tasks for backend-dev:**
- Create migration file
- Create all model files
- Create enum files
- Write model unit tests

---

### Phase 2: Hold Management (Week 2)
**Dependencies:** Phase 1

**Deliverables:**
1. `TicketHoldPolicy`
2. `CreateTicketHoldAction`
3. `UpdateTicketHoldAction`
4. `ReleaseTicketHoldAction`
5. `ValidateHoldAvailabilityAction`
6. `TicketHoldService`
7. Admin controllers for holds
8. Feature tests

**Tasks for backend-dev:**
- Create policy
- Create all hold-related actions
- Create service
- Create admin controller
- Write feature tests

---

### Phase 3: Link Management (Week 3)
**Dependencies:** Phase 2

**Deliverables:**
1. `PurchaseLinkPolicy`
2. `CreatePurchaseLinkAction`
3. `UpdatePurchaseLinkAction`
4. `RevokePurchaseLinkAction`
5. `RecordLinkAccessAction`
6. Admin controllers for links
7. Feature tests

**Tasks for backend-dev:**
- Create policy
- Create all link-related actions
- Create admin controller
- Write feature tests

---

### Phase 4: Purchase Flow (Week 4)
**Dependencies:** Phase 3

**Deliverables:**
1. `ProcessHoldPurchaseAction`
2. `CalculateHoldPriceAction`
3. Public purchase link controller
4. Integration with existing `BookingService` patterns
5. Stripe checkout integration
6. Feature tests for purchase flow

**Tasks for backend-dev:**
- Create purchase actions
- Create public controller
- Integrate with payment system
- Write integration tests

---

### Phase 5: Admin Frontend (Week 5)
**Dependencies:** Phase 2, 3

**Deliverables:**
1. Hold CRUD pages (Index, Create, Edit, Show)
2. Link CRUD pages
3. Form components
4. Status badges and displays
5. Navigation integration

**Tasks for frontend-dev:**
- Create all Vue pages
- Create reusable components
- Integrate with backend APIs
- Add to admin navigation

---

### Phase 6: Public Frontend (Week 6)
**Dependencies:** Phase 4

**Deliverables:**
1. Purchase link landing page
2. Ticket selector component
3. Price display with savings
4. Checkout flow integration
5. Error state handling

**Tasks for frontend-dev:**
- Create public link page
- Create selection components
- Handle auth/unauth states
- Style for public access

---

### Phase 7: Analytics & Polish (Week 7)
**Dependencies:** Phase 5, 6

**Deliverables:**
1. `HoldAnalyticsService`
2. Analytics dashboard components
3. Charts and visualizations
4. Export functionality
5. Documentation

**Tasks for backend-dev:**
- Create analytics service
- Create analytics endpoints

**Tasks for frontend-dev:**
- Create analytics pages
- Add charts/graphs
- Create export UI

---

### Phase 8: Coupon Integration & Testing (Week 8)
**Dependencies:** Phase 4, 6

**Deliverables:**
1. Coupon code support in purchase flow
2. End-to-end tests
3. Performance testing
4. Bug fixes and polish

**Tasks for backend-dev:**
- Integrate coupon validation
- Write E2E tests
- Performance optimization

**Tasks for frontend-dev:**
- Add coupon input to purchase page
- Final polish and QA

---

## 16. Task Breakdown for Delegation

### 16.1 Backend Developer Tasks

#### Task B1: Database & Models (Phase 1)
```
Agent: backend-dev
Dependencies: None
TDD Spec:
  - Test: Model relationships work correctly
  - Test: Scopes filter as expected
  - Test: Accessors calculate correctly
  - Impl: Migration, Models, Enums
Constraints:
  - Follow existing model patterns (TicketDefinition, Coupon)
  - Use soft deletes on TicketHold and PurchaseLink
  - Include proper indexes
Acceptance Criteria:
  - All migrations run successfully
  - All model tests pass
  - Relationships queryable
```

#### Task B2: Hold Actions (Phase 2)
```
Agent: backend-dev
Dependencies: B1
TDD Spec:
  - Test: CreateTicketHoldAction validates inventory
  - Test: CreateTicketHoldAction creates allocations
  - Test: ValidateHoldAvailabilityAction calculates correctly
  - Test: ReleaseTicketHoldAction updates status
  - Impl: Actions in app/Modules/TicketHold/Actions/
Constraints:
  - Use DB transactions
  - Throw typed exceptions
  - Return loaded relationships
Acceptance Criteria:
  - All action tests pass
  - Inventory validation prevents over-allocation
```

#### Task B3: Hold Controller & Policy (Phase 2)
```
Agent: backend-dev
Dependencies: B2
TDD Spec:
  - Test: Admin can create hold
  - Test: Organizer can create hold for their events
  - Test: Organizer cannot create hold for other's events
  - Test: Hold CRUD operations work
  - Impl: TicketHoldController, TicketHoldPolicy
Constraints:
  - Follow existing AdminBookingController patterns
  - Return Inertia responses
  - Apply policy authorization
Acceptance Criteria:
  - All feature tests pass
  - Authorization works correctly
```

#### Task B4: Link Actions (Phase 3)
```
Agent: backend-dev
Dependencies: B2
TDD Spec:
  - Test: CreatePurchaseLinkAction generates unique code
  - Test: RevokePurchaseLinkAction updates status
  - Test: RecordLinkAccessAction creates access record
  - Impl: Link-related actions
Constraints:
  - Unique code generation must be collision-free
  - Links inherit hold's organizer scope
Acceptance Criteria:
  - All link action tests pass
  - Link codes are unique
```

#### Task B5: Purchase Flow (Phase 4)
```
Agent: backend-dev
Dependencies: B4
TDD Spec:
  - Test: ProcessHoldPurchaseAction validates link usability
  - Test: ProcessHoldPurchaseAction respects quantity limits
  - Test: ProcessHoldPurchaseAction calculates correct prices
  - Test: ProcessHoldPurchaseAction creates bookings
  - Test: Free purchases confirm immediately
  - Test: Paid purchases redirect to Stripe
  - Impl: ProcessHoldPurchaseAction, public controller
Constraints:
  - Follow existing BookingService patterns
  - Use DB locks for concurrency
  - Integrate with existing Stripe setup
Acceptance Criteria:
  - All purchase tests pass
  - Bookings created correctly
  - Payment flow works
```

#### Task B6: Analytics Service (Phase 7)
```
Agent: backend-dev
Dependencies: B5
TDD Spec:
  - Test: Hold analytics calculate correctly
  - Test: Link analytics aggregate properly
  - Test: Daily access counts are accurate
  - Impl: HoldAnalyticsService, analytics endpoints
Constraints:
  - Optimize queries for large datasets
  - Support date range filtering
Acceptance Criteria:
  - Analytics data accurate
  - Performance acceptable
```

### 16.2 Frontend Developer Tasks

#### Task F1: Hold Admin Pages (Phase 5)
```
Agent: frontend-dev
Dependencies: B3
TDD Spec:
  - Test: Hold list renders correctly
  - Test: Hold form validates input
  - Test: Allocation table allows add/remove
  - Impl: Index.vue, Create.vue, Edit.vue, Show.vue
Constraints:
  - Follow existing Admin page patterns
  - Use existing form components
  - Support multilingual ticket names
Acceptance Criteria:
  - All pages render correctly
  - Forms submit successfully
  - Validation errors display
```

#### Task F2: Link Admin Pages (Phase 5)
```
Agent: frontend-dev
Dependencies: F1
TDD Spec:
  - Test: Link list renders with status badges
  - Test: Link form handles quantity modes
  - Test: User search autocomplete works
  - Test: Copy URL button copies to clipboard
  - Impl: Link pages and components
Constraints:
  - Follow existing patterns
  - Integrate user search from existing components
Acceptance Criteria:
  - All pages functional
  - URL copying works
  - Status badges display correctly
```

#### Task F3: Public Purchase Page (Phase 6)
```
Agent: frontend-dev
Dependencies: B5
TDD Spec:
  - Test: Page shows event details
  - Test: Ticket selector shows availability
  - Test: Price shows savings when discounted
  - Test: Auth state handled correctly
  - Impl: Public/PurchaseLink/Show.vue
Constraints:
  - Works for both auth and unauth users
  - Clear messaging for link restrictions
  - Mobile responsive
Acceptance Criteria:
  - Public page accessible
  - Purchase flow works
  - Error states handled
```

#### Task F4: Analytics Dashboard (Phase 7)
```
Agent: frontend-dev
Dependencies: B6
TDD Spec:
  - Test: Hold analytics display correctly
  - Test: Link analytics show charts
  - Test: Date range filter works
  - Impl: Analytics pages and chart components
Constraints:
  - Use chart library (Chart.js or similar)
  - Support date range selection
Acceptance Criteria:
  - Analytics data displays
  - Charts render correctly
  - Filters work
```

---

## Appendix A: File Structure Summary

```
app/Modules/TicketHold/
├── Actions/
│   ├── CreateTicketHoldAction.php
│   ├── UpdateTicketHoldAction.php
│   ├── ReleaseTicketHoldAction.php
│   ├── CreatePurchaseLinkAction.php
│   ├── UpdatePurchaseLinkAction.php
│   ├── RevokePurchaseLinkAction.php
│   ├── RecordLinkAccessAction.php
│   ├── ProcessHoldPurchaseAction.php
│   ├── ValidateHoldAvailabilityAction.php
│   ├── CalculateHoldPriceAction.php
│   └── ExpireHoldsAction.php
├── Controllers/
│   ├── Admin/
│   │   ├── TicketHoldController.php
│   │   ├── PurchaseLinkController.php
│   │   ├── HoldAnalyticsController.php
│   │   └── LinkAnalyticsController.php
│   └── Public/
│       └── PurchaseLinkController.php
├── DataTransferObjects/
│   ├── TicketHoldData.php
│   ├── TicketAllocationData.php
│   ├── PurchaseLinkData.php
│   ├── HoldPurchaseRequestData.php
│   └── HoldPurchaseItemData.php
├── Enums/
│   ├── HoldStatusEnum.php
│   ├── LinkStatusEnum.php
│   ├── PricingModeEnum.php
│   └── QuantityModeEnum.php
├── Exceptions/
│   ├── HoldNotActiveException.php
│   ├── LinkNotUsableException.php
│   ├── UserNotAuthorizedForLinkException.php
│   ├── InsufficientInventoryException.php
│   └── InsufficientHoldInventoryException.php
├── Models/
│   ├── TicketHold.php
│   ├── HoldTicketAllocation.php
│   ├── PurchaseLink.php
│   ├── PurchaseLinkAccess.php
│   └── PurchaseLinkPurchase.php
├── Policies/
│   ├── TicketHoldPolicy.php
│   └── PurchaseLinkPolicy.php
└── Services/
    ├── TicketHoldService.php
    └── HoldAnalyticsService.php

database/migrations/
└── 2025_12_24_000000_create_ticket_holds_tables.php

resources/js/Pages/Admin/TicketHolds/
├── Index.vue
├── Create.vue
├── Edit.vue
├── Show.vue
└── components/
    ├── HoldForm.vue
    ├── AllocationTable.vue
    ├── HoldStatusBadge.vue
    └── HoldStats.vue

resources/js/Pages/Admin/PurchaseLinks/
├── Index.vue
├── Create.vue
├── Edit.vue
├── Show.vue
└── components/
    ├── LinkForm.vue
    ├── LinkUrlDisplay.vue
    ├── LinkStatusBadge.vue
    ├── UsageChart.vue
    └── AccessLog.vue

resources/js/Pages/Public/PurchaseLink/
├── Show.vue
└── components/
    ├── HoldTicketSelector.vue
    ├── HoldPriceDisplay.vue
    └── HoldCheckoutButton.vue

tests/
├── Feature/
│   └── Modules/
│       └── TicketHold/
│           ├── CreateTicketHoldTest.php
│           ├── ReleaseTicketHoldTest.php
│           ├── CreatePurchaseLinkTest.php
│           ├── RevokePurchaseLinkTest.php
│           ├── ProcessHoldPurchaseTest.php
│           └── HoldAnalyticsTest.php
└── Unit/
    └── Modules/
        └── TicketHold/
            ├── TicketHoldModelTest.php
            ├── PurchaseLinkModelTest.php
            ├── HoldTicketAllocationTest.php
            └── PriceCalculationTest.php
```

---

## Appendix B: Scheduled Tasks

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Expire holds and links past their expiration date
    $schedule->call(function () {
        app(\App\Modules\TicketHold\Actions\ExpireHoldsAction::class)->execute();
    })->hourly();
}
```

---

## Appendix C: Configuration

Consider adding a config file for tunables:

**File: `config/ticket-hold.php`**

```php
<?php

return [
    // Default link code length
    'link_code_length' => 16,

    // Default hold expiration (null = no default)
    'default_hold_expiration_days' => null,

    // Default link expiration (null = no default)
    'default_link_expiration_days' => 30,

    // Maximum allocations per hold
    'max_allocations_per_hold' => 50,

    // Maximum links per hold
    'max_links_per_hold' => 1000,

    // Rate limiting for public link access
    'public_link_rate_limit' => 60, // per minute

    // Rate limiting for purchase attempts
    'purchase_rate_limit' => 10, // per minute
];
```

---

**Document End**

*This specification is ready for implementation. Each phase can be delegated to the appropriate sub-agent (backend-dev or frontend-dev) using the task specs in Section 16.*
