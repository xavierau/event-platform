<?php

namespace App\Models;

use App\Enums\OrganizerRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;
use App\Traits\Commentable;

class Organizer extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia, SoftDeletes, Commentable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'website_url',
        'contact_email',
        'contact_phone',
        'social_media_links',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'country_id',
        'state_id',
        'is_active',
        'contract_details',
        'comments_enabled',
        'comments_require_approval',
        'created_by',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'social_media_links' => 'json',
        'contract_details' => 'json',
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Define media collections for organizer logos and media.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Define media conversions for different image sizes.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->performOnCollections('logo', 'gallery');

        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('logo', 'gallery');
    }

    /**
     * Relationship with the user who created this organizer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with country.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relationship with state.
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Many-to-many relationship with users through organizer_users pivot.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizer_users')
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
     * Get only active team members.
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_active', true);
    }

    /**
     * Get team members with specific role.
     */
    public function usersByRole(OrganizerRoleEnum $role): BelongsToMany
    {
        return $this->activeUsers()->wherePivot('role_in_organizer', $role->value);
    }

    /**
     * Get organizer owners.
     */
    public function owners(): BelongsToMany
    {
        return $this->usersByRole(OrganizerRoleEnum::OWNER);
    }

    /**
     * Get organizer managers.
     */
    public function managers(): BelongsToMany
    {
        return $this->usersByRole(OrganizerRoleEnum::MANAGER);
    }

    /**
     * Get organizer staff.
     */
    public function staff(): BelongsToMany
    {
        return $this->usersByRole(OrganizerRoleEnum::STAFF);
    }

    /**
     * Get organizer viewers.
     */
    public function viewers(): BelongsToMany
    {
        return $this->usersByRole(OrganizerRoleEnum::VIEWER);
    }

    /**
     * Relationship with events organized by this organizer.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Relationship with venues owned by this organizer.
     */
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    /**
     * Check if a user is a member of this organizer.
     */
    public function hasMember(User $user): bool
    {
        return $this->activeUsers()->where('user_id', $user->id)->exists();
    }

    /**
     * Get a user's role in this organizer.
     */
    public function getUserRole(User $user): ?OrganizerRoleEnum
    {
        $pivot = $this->users()->where('user_id', $user->id)->first()?->pivot;

        if (!$pivot || !$pivot->is_active) {
            return null;
        }

        return OrganizerRoleEnum::tryFrom($pivot->role_in_organizer);
    }

    /**
     * Check if a user has a specific role in this organizer.
     */
    public function userHasRole(User $user, OrganizerRoleEnum $role): bool
    {
        return $this->getUserRole($user) === $role;
    }

    /**
     * Check if a user can manage this organizer.
     */
    public function userCanManage(User $user): bool
    {
        $role = $this->getUserRole($user);
        return $role && $role->canManageOrganizer();
    }

    /**
     * Check if a user can manage users of this organizer.
     */
    public function userCanManageUsers(User $user): bool
    {
        $role = $this->getUserRole($user);
        return $role && $role->canManageUsers();
    }

    /**
     * Check if a user can manage events of this organizer.
     */
    public function userCanManageEvents(User $user): bool
    {
        // First check if user has the capability through their trait method (includes permissions check)
        if ($user->canManageOrganizerEvents($this)) {
            return true;
        }

        // Fallback to role-based check for compatibility
        $role = $this->getUserRole($user);
        return $role && $role->canManageEvents();
    }

    /**
     * Scope to get only active organizers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search organizers by name.
     */
    public function scopeSearchByName($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name->en', 'like', "%{$search}%")
                ->orWhere('name->zh-TW', 'like', "%{$search}%")
                ->orWhere('name->zh-CN', 'like', "%{$search}%");
        });
    }

    /**
     * Get the full address as a string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state?->name,
            $this->postal_code,
            $this->country?->name,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get the primary logo media.
     */
    public function getLogoAttribute(): ?Media
    {
        return $this->getFirstMedia('logo');
    }

    /**
     * Get the logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $logo = $this->getFirstMedia('logo');
        return $logo ? $logo->getUrl() : null;
    }

    /**
     * Get the logo thumbnail URL.
     */
    public function getLogoThumbUrlAttribute(): ?string
    {
        $logo = $this->getFirstMedia('logo');
        return $logo ? $logo->getUrl('thumb') : null;
    }
}
