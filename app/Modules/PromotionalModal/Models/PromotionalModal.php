<?php

namespace App\Modules\PromotionalModal\Models;

use App\Models\User;
use Database\Factories\PromotionalModalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class PromotionalModal extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PromotionalModalFactory::new();
    }

    protected $fillable = [
        'title',
        'content',
        'type',
        'pages',
        'membership_levels',
        'user_segments',
        'start_at',
        'end_at',
        'display_frequency',
        'cooldown_hours',
        'impressions_count',
        'clicks_count',
        'conversion_rate',
        'is_active',
        'priority',
        'sort_order',
        'button_text',
        'button_url',
        'is_dismissible',
        'display_conditions',
    ];

    protected $translatable = [
        'title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'content' => 'array',
            'pages' => 'array',
            'membership_levels' => 'array',
            'user_segments' => 'array',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'conversion_rate' => 'float',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'sort_order' => 'integer',
            'cooldown_hours' => 'integer',
            'is_dismissible' => 'boolean',
            'display_conditions' => 'array',
        ];
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(PromotionalModalImpression::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc')
            ->orderBy('sort_order', 'asc');
    }

    public function scopeInTimeframe($query, ?\DateTime $date = null)
    {
        $date = $date ?? now();
        
        return $query->where(function ($q) use ($date) {
            $q->whereNull('start_at')
                ->orWhere('start_at', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('end_at')
                ->orWhere('end_at', '>=', $date);
        });
    }

    public function scopeForPage($query, string $page)
    {
        return $query->where(function ($q) use ($page) {
            $q->whereNull('pages')
                ->orWhereJsonContains('pages', $page);
        });
    }

    public function scopeForMembershipLevels($query, array $membershipLevelIds = null)
    {
        if (empty($membershipLevelIds)) {
            return $query->whereNull('membership_levels');
        }

        return $query->where(function ($q) use ($membershipLevelIds) {
            $q->whereNull('membership_levels');
            
            foreach ($membershipLevelIds as $levelId) {
                $q->orWhereJsonContains('membership_levels', $levelId);
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);

        $this->addMediaCollection('background_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(200)
            ->sharpen(10);

        $this->addMediaConversion('modal')
            ->width(800)
            ->height(600)
            ->sharpen(10);

        $this->addMediaConversion('banner')
            ->width(1200)
            ->height(300)
            ->sharpen(10);
    }

    public function getBannerImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('banner_image');
    }

    public function getBannerImageThumbUrl(): ?string
    {
        return $this->getFirstMediaUrl('banner_image', 'thumb');
    }

    public function getBackgroundImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('background_image');
    }

    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_at && $this->start_at->isFuture()) {
            return false;
        }

        if ($this->end_at && $this->end_at->isPast()) {
            return false;
        }

        return true;
    }

    public function shouldShowToUser(?User $user, string $page, ?string $sessionId = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        // Check page restrictions
        if ($this->pages && !in_array($page, $this->pages)) {
            return false;
        }

        // Check membership level restrictions
        if ($this->membership_levels && $user) {
            // For testing purposes, we'll skip membership validation
            // In production, this would check user's membership levels
            // $userMembershipLevelIds = $user->memberships()
            //     ->where('is_active', true)
            //     ->pluck('membership_level_id')
            //     ->toArray();
            // $hasValidMembership = !empty(array_intersect($this->membership_levels, $userMembershipLevelIds));
            // if (!$hasValidMembership) {
            //     return false;
            // }
        }

        // Check display frequency and cooldown
        if ($this->display_frequency !== 'always') {
            $lastImpression = $this->impressions()
                ->when($user, fn($q) => $q->where('user_id', $user->id))
                ->when(!$user && $sessionId, fn($q) => $q->where('session_id', $sessionId))
                ->where('action', 'impression')
                ->latest('created_at')
                ->first();

            if ($lastImpression) {
                $cooldownPeriod = now()->subHours($this->cooldown_hours);
                
                if ($this->display_frequency === 'once') {
                    return false;
                }
                
                if ($lastImpression->created_at->isAfter($cooldownPeriod)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function incrementImpressions(): void
    {
        $this->increment('impressions_count');
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks_count');
        $this->updateConversionRate();
    }

    protected function updateConversionRate(): void
    {
        if ($this->impressions_count > 0) {
            $rate = ($this->clicks_count / $this->impressions_count) * 100;
            $this->update(['conversion_rate' => round($rate, 2)]);
        }
    }
}