<?php

namespace App\Modules\Membership\Models;

use App\Modules\Membership\Models\UserMembership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class MembershipLevel extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'membership_levels';

    public array $translatable = ['name', 'description', 'benefits'];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'points_cost',
        'kill_points_cost',
        'duration_months',
        'benefits',
        'max_users',
        'is_active',
        'sort_order',
        'metadata',
        'stripe_product_id',
        'stripe_price_id',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price' => 'integer',
        'points_cost' => 'integer',
        'kill_points_cost' => 'integer',
        'duration_months' => 'integer',
        'benefits' => 'array',
        'max_users' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get all user memberships for this level.
     */
    public function userMemberships(): HasMany
    {
        return $this->hasMany(UserMembership::class);
    }

    /**
     * Get active user memberships for this level.
     */
    public function activeUserMemberships(): HasMany
    {
        return $this->userMemberships()->where('status', 'active');
    }

    /**
     * Scope for active membership levels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Check if this membership level has reached its user limit.
     */
    public function hasReachedUserLimit(): bool
    {
        if (!$this->max_users) {
            return false;
        }

        return $this->activeUserMemberships()->count() >= $this->max_users;
    }

    /**
     * Get the number of available slots for this membership level.
     */
    public function getAvailableSlots(): ?int
    {
        if (!$this->max_users) {
            return null; // Unlimited
        }

        return max(0, $this->max_users - $this->activeUserMemberships()->count());
    }

    /**
     * Check if a specific benefit is included in this membership level.
     */
    public function hasBenefit(string $benefit): bool
    {
        return in_array($benefit, $this->benefits ?? []);
    }
}
