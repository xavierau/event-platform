<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Cashier\Billable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Traits\HasOrganizerPermissions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Billable, HasOrganizerPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
        'is_commenting_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_commenting_blocked' => 'boolean',
            'stripe_customer_ids' => 'array',
        ];
    }

    /**
     * Get the events that this user has wishlisted.
     */
    public function wishlistedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'user_event_wishlists')->withTimestamps();
    }

    /**
     * Add an event to the user's wishlist.
     */
    public function addToWishlist(Event $event): void
    {
        if (!$this->hasInWishlist($event)) {
            $this->wishlistedEvents()->attach($event->id);
        }
    }

    /**
     * Remove an event from the user's wishlist.
     */
    public function removeFromWishlist(Event $event): void
    {
        $this->wishlistedEvents()->detach($event->id);
    }

    /**
     * Check if the user has the event in their wishlist.
     */
    public function hasInWishlist(Event $event): bool
    {
        return $this->wishlistedEvents()->where('event_id', $event->id)->exists();
    }

    /**
     * Get the user's wallet.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get the user's current membership.
     */
    public function currentMembership()
    {
        return $this->hasOne(UserMembership::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest();
    }

    /**
     * Get all user memberships.
     */
    public function memberships()
    {
        return $this->hasMany(UserMembership::class);
    }

    /**
     * Check if the user has an active membership.
     */
    public function hasMembership(): bool
    {
        return $this->currentMembership()->exists();
    }

    /**
     * Get the user's points balance.
     */
    public function getPointsBalance(): int
    {
        return $this->wallet?->points_balance ?? 0;
    }

    /**
     * Get the user's kill points balance.
     */
    public function getKillPointsBalance(): int
    {
        return $this->wallet?->kill_points_balance ?? 0;
    }

    /**
     * Check if the user has enough points.
     */
    public function hasEnoughPoints(int $amount): bool
    {
        return $this->getPointsBalance() >= $amount;
    }

    /**
     * Check if the user has enough kill points.
     */
    public function hasEnoughKillPoints(int $amount): bool
    {
        return $this->getKillPointsBalance() >= $amount;
    }

    /**
     * Get organizers that this user belongs to.
     */
    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class, 'organizer_users')
            ->withPivot([
                'role_in_organizer',
                'permissions',
                'joined_at',
                'is_active',
                'invited_by',
                'invitation_accepted_at'
            ])
            ->withTimestamps();
    }

    /**
     * Get active organizer memberships.
     */
    public function activeOrganizers(): BelongsToMany
    {
        return $this->organizers()->wherePivot('is_active', true);
    }

    /**
     * Get organizers where user has a specific role.
     */
    public function organizersByRole(\App\Enums\OrganizerRoleEnum $role): BelongsToMany
    {
        return $this->activeOrganizers()->wherePivot('role_in_organizer', $role->value);
    }

    /**
     * Get organizers where user is an owner.
     */
    public function ownedOrganizers(): BelongsToMany
    {
        return $this->organizersByRole(\App\Enums\OrganizerRoleEnum::OWNER);
    }

    /**
     * Get organizers where user is a manager.
     */
    public function managedOrganizers(): BelongsToMany
    {
        return $this->organizersByRole(\App\Enums\OrganizerRoleEnum::MANAGER);
    }

    /**
     * Check if user belongs to any organizer.
     */
    public function hasOrganizerMembership(): bool
    {
        return $this->activeOrganizers()->exists();
    }

    /**
     * Check if user has a specific role in any organizer.
     */
    public function hasOrganizerRole(\App\Enums\OrganizerRoleEnum $role): bool
    {
        return $this->organizersByRole($role)->exists();
    }

    /**
     * Check if user is an organizer owner.
     */
    public function isOrganizerOwner(): bool
    {
        return $this->hasOrganizerRole(\App\Enums\OrganizerRoleEnum::OWNER);
    }

    /**
     * Check if user is an organizer member (any role).
     */
    public function isOrganizerMember(): bool
    {
        return $this->hasOrganizerMembership();
    }

    /**
     * Get user's role in a specific organizer.
     */
    public function getOrganizerRole(Organizer $organizer): ?\App\Enums\OrganizerRoleEnum
    {
        $pivot = $this->organizers()->where('organizer_id', $organizer->id)->first()?->pivot;

        if (!$pivot || !$pivot->is_active) {
            return null;
        }

        return \App\Enums\OrganizerRoleEnum::tryFrom($pivot->role_in_organizer);
    }

    /**
     * Check if user is a member of a specific organizer.
     */
    public function isMemberOfOrganizer(Organizer $organizer): bool
    {
        return !is_null($this->getOrganizerRole($organizer));
    }

    /**
     * Check if user can manage a specific organizer.
     */
    public function canManageOrganizer(Organizer $organizer): bool
    {
        $role = $this->getOrganizerRole($organizer);
        return $role && $role->canManageOrganizer();
    }

    /**
     * Check if user can manage users in a specific organizer.
     */
    public function canManageOrganizerUsers(Organizer $organizer): bool
    {
        $role = $this->getOrganizerRole($organizer);
        return $role && $role->canManageUsers();
    }

    /**
     * Get all organizers that user can manage events for.
     * Uses the HasOrganizerPermissions trait method for fine-grained permission checking.
     */
    public function getEventManageableOrganizers(): BelongsToMany
    {
        // Get organizers where user has event management permissions
        $organizerIds = $this->getOrganizersWhereCanManageEvents()->pluck('id');

        return $this->activeOrganizers()->whereIn('organizer_id', $organizerIds);
    }

    public function membership(): HasOne
    {
        return $this->hasOne(UserMembership::class)->ofMany('started_at', 'max');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Check if user has a specific Stripe customer ID.
     */
    public function hasStripeCustomerId(string $customerId): bool
    {
        // Check primary stripe_id
        if ($this->stripe_id === $customerId) {
            return true;
        }

        // Check additional customer IDs
        $customerIds = $this->stripe_customer_ids ?? [];
        return in_array($customerId, $customerIds);
    }

    /**
     * Add a Stripe customer ID to the user's collection.
     */
    public function addStripeCustomerId(string $customerId): void
    {
        // If no primary stripe_id, set it as primary
        if (!$this->stripe_id) {
            $this->stripe_id = $customerId;
            $this->save();
            return;
        }

        // If already the primary, nothing to do
        if ($this->stripe_id === $customerId) {
            return;
        }

        // Add to additional customer IDs if not already present
        $customerIds = $this->stripe_customer_ids ?? [];
        if (!in_array($customerId, $customerIds)) {
            $customerIds[] = $customerId;
            $this->stripe_customer_ids = $customerIds;
            $this->save();
        }
    }

    /**
     * Get all Stripe customer IDs for this user.
     */
    public function getAllStripeCustomerIds(): array
    {
        $ids = [];
        
        if ($this->stripe_id) {
            $ids[] = $this->stripe_id;
        }

        if ($this->stripe_customer_ids) {
            $ids = array_merge($ids, $this->stripe_customer_ids);
        }

        return array_unique($ids);
    }

    /**
     * Scope to find users by any of their Stripe customer IDs.
     */
    public function scopeWithStripeCustomerId($query, string $customerId)
    {
        return $query->where(function ($q) use ($customerId) {
            $q->where('stripe_id', $customerId)
              ->orWhereJsonContains('stripe_customer_ids', $customerId);
        });
    }
}
