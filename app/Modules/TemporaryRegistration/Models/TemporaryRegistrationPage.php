<?php

namespace App\Modules\TemporaryRegistration\Models;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Database\Eloquent\Builder;
use Database\Factories\TemporaryRegistrationPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class TemporaryRegistrationPage extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia;

    protected $table = 'temporary_registration_pages';

    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'title',
        'description',
        'slug',
        'token',
        'membership_level_id',
        'expires_at',
        'duration_days',
        'max_registrations',
        'registrations_count',
        'is_active',
        'use_slug',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'expires_at' => 'datetime',
            'duration_days' => 'integer',
            'max_registrations' => 'integer',
            'registrations_count' => 'integer',
            'is_active' => 'boolean',
            'use_slug' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TemporaryRegistrationPage $model) {
            if (empty($model->token)) {
                $model->token = Str::random(32);
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TemporaryRegistrationPageFactory
    {
        return TemporaryRegistrationPageFactory::new();
    }

    /**
     * Get the membership level associated with this registration page.
     */
    public function membershipLevel(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class);
    }

    /**
     * Get the user who created this registration page.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the users who registered through this page.
     */
    public function registeredUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'temporary_registration_page_users')
            ->withPivot(['ip_address', 'user_agent'])
            ->withTimestamps();
    }

    /**
     * Scope for active registration pages.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for registration pages that have not expired.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for registration pages that still have capacity.
     */
    public function scopeHasCapacity(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('max_registrations')
                ->orWhereColumn('registrations_count', '<', 'max_registrations');
        });
    }

    /**
     * Scope for registration pages that are available (active, not expired, has capacity).
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->notExpired()->hasCapacity();
    }

    /**
     * Get the public URL for this registration page.
     */
    public function getPublicUrl(): string
    {
        $identifier = $this->use_slug ? $this->slug : $this->token;

        return route('register.temporary', $identifier);
    }

    /**
     * Check if this registration page is available for new registrations.
     */
    public function isAvailable(): bool
    {
        return $this->is_active && !$this->isExpired() && !$this->isFull();
    }

    /**
     * Check if this registration page has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if this registration page has reached its maximum registrations.
     */
    public function isFull(): bool
    {
        if ($this->max_registrations === null) {
            return false;
        }

        return $this->registrations_count >= $this->max_registrations;
    }

    /**
     * Get the number of remaining registration slots.
     */
    public function getRemainingSlots(): ?int
    {
        if ($this->max_registrations === null) {
            return null;
        }

        return max(0, $this->max_registrations - $this->registrations_count);
    }

    /**
     * Increment the registration count.
     */
    public function incrementRegistrationCount(): void
    {
        $this->increment('registrations_count');
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Get the banner image URL.
     */
    public function getBannerUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('banner');

        return $url !== '' ? $url : null;
    }
}
