<?php

namespace App\Models;

use App\Enums\CommentConfigEnum;
use App\Helpers\CurrencyHelper;
use App\Traits\Commentable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Event extends Model implements HasMedia
{
    use Commentable, HasFactory, HasTranslations, InteractsWithMedia, SoftDeletes;

    public const EVENT_STATUSES = [
        'draft',
        'pending_approval',
        'published',
        'cancelled',
        'completed',
        'past',
    ];

    public const VISIBILITIES = [
        'public',
        'private',
        'unlisted',
    ];

    public const ACTION_TYPES = [
        'purchase_ticket' => 'Purchase Ticket',
        'show_member_qr' => 'Show Member QR',
    ];

    protected $fillable = [
        'organizer_id',
        'category_id',
        'name',
        'slug',
        'description',
        'short_summary',
        'event_status',
        'visibility',
        'visible_to_membership_levels',
        'action_type',
        'redirect_url',
        'is_featured',
        'contact_email',
        'contact_phone',
        'website_url',
        'social_media_links',
        'youtube_video_id',
        'cancellation_policy',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
        'created_by',
        'updated_by',
        'comment_config',
        'comments_enabled',
        'comments_require_approval',
        'seating_chart',
    ];

    public array $translatable = [
        'name',
        'slug',
        'description',
        'short_summary',
        'cancellation_policy',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'name' => 'array',
        'slug' => 'array',
        'description' => 'array',
        'short_summary' => 'array',
        'cancellation_policy' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'meta_keywords' => 'array',
        'social_media_links' => 'json',
        'visible_to_membership_levels' => 'json',
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'comments_enabled' => 'boolean',
        'comments_require_approval' => 'boolean',
        'comment_config' => CommentConfigEnum::class,
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'event_tag');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function eventOccurrences()
    {
        return $this->hasMany(EventOccurrence::class);
    }

    // public function ticketDefinitions() // This relationship is no longer directly valid as TicketDefinition does not have event_id.
    // { // Access TicketDefinitions through EventOccurrences: $event->eventOccurrences()->with('ticketDefinitions')->get();
    //     // Or define a hasManyThrough relationship if needed for specific use cases.
    //     return $this->hasMany(TicketDefinition::class);
    // }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the organizer entity for this event.
     * Alias for organizer() for clarity.
     */
    public function organizerEntity()
    {
        return $this->organizer();
    }

    /**
     * Check if the event has an organizer.
     */
    public function hasOrganizer(): bool
    {
        return ! is_null($this->organizer_id);
    }

    /**
     * Get the organizer name in the current locale.
     */
    public function getOrganizerName(): ?string
    {
        return $this->organizer?->getTranslation('name', app()->getLocale());
    }

    /**
     * Check if a user can manage this event through their organizer membership.
     */
    public function canBeEditedByUser(User $user): bool
    {
        if (! $this->organizer) {
            return false;
        }

        return $this->organizer->userCanManageEvents($user);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('portrait_poster')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        $this->addMediaCollection('landscape_poster')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->performOnCollections('portrait_poster', 'landscape_poster', 'gallery');

        $this->addMediaConversion('medium')
            ->width(800)
            ->height(600)
            ->sharpen(10)
            ->performOnCollections('portrait_poster', 'landscape_poster', 'gallery');
    }

    /**
     * Calculate the price range for this event across all occurrences and tickets
     * Only considers tickets that are currently available within their availability window
     *
     * @param  \App\Models\User|null  $user  User to calculate membership pricing for
     * @return string|null Formatted price range or null if no tickets available
     */
    public function getPriceRange(?\App\Models\User $user = null): ?string
    {
        // Ensure eventOccurrences and their ticketDefinitions are loaded
        if (! $this->relationLoaded('eventOccurrences')) {
            $this->load('eventOccurrences.ticketDefinitions');
        }

        $nowUtc = now()->utc();

        // Calculate price range across all occurrences and tickets
        // Only include tickets that are currently available
        $allPrices = $this->eventOccurrences->flatMap(function ($occurrence) use ($nowUtc, $user) {
            return $occurrence->ticketDefinitions->filter(function ($ticket) use ($nowUtc) {
                // Check if ticket is currently available based on availability window
                if ($ticket->availability_window_start_utc === null && $ticket->availability_window_end_utc === null) {
                    // No availability window set - ticket is available
                    return true;
                }

                // Check if current time is within availability window
                // Handle cases where only start or only end time is set
                $afterStart = $ticket->availability_window_start_utc === null || $ticket->availability_window_start_utc <= $nowUtc;
                $beforeEnd = $ticket->availability_window_end_utc === null || $ticket->availability_window_end_utc >= $nowUtc;

                return $afterStart && $beforeEnd;
            })->map(function ($ticket) use ($user) {
                // Use price_override if available, otherwise use original price
                $basePrice = $ticket->pivot->price_override ?? $ticket->price;

                // If user is provided and has membership, calculate membership pricing on the effective price
                if ($user) {
                    $activeMembershipLevel = $user->getActiveMembershipLevel();

                    if ($activeMembershipLevel) {
                        // Check if this ticket has a discount for the user's membership level
                        $discount = $ticket->membershipDiscounts()
                            ->where('membership_level_id', $activeMembershipLevel->id)
                            ->first();

                        if ($discount) {
                            // Apply discount to the effective price (which includes price overrides)
                            if ($discount->pivot->discount_type === 'percentage') {
                                $discountAmount = round($basePrice * ($discount->pivot->discount_value / 100));

                                return max(0, $basePrice - $discountAmount);
                            } elseif ($discount->pivot->discount_type === 'fixed') {
                                return max(0, $basePrice - $discount->pivot->discount_value);
                            }
                        }
                    }
                }

                return $basePrice;
            });
        })->filter()->values();

        if ($allPrices->isEmpty()) {
            return null;
        }

        $minPrice = $allPrices->min();
        $maxPrice = $allPrices->max();

        // Get currency from first available ticket
        $firstAvailableTicket = $this->eventOccurrences->flatMap(function ($occurrence) use ($nowUtc) {
            return $occurrence->ticketDefinitions->filter(function ($ticket) use ($nowUtc) {
                if ($ticket->availability_window_start_utc === null && $ticket->availability_window_end_utc === null) {
                    return true;
                }
                // Handle cases where only start or only end time is set
                $afterStart = $ticket->availability_window_start_utc === null || $ticket->availability_window_start_utc <= $nowUtc;
                $beforeEnd = $ticket->availability_window_end_utc === null || $ticket->availability_window_end_utc >= $nowUtc;

                return $afterStart && $beforeEnd;
            });
        })->first();

        $currencyCode = $firstAvailableTicket ? $firstAvailableTicket->currency : CurrencyHelper::getDefault();

        // Format price range using helper
        return CurrencyHelper::formatRange($minPrice, $maxPrice, $currencyCode);
    }

    /**
     * Get the primary venue for this event
     *
     * @return \App\Models\Venue|null The primary venue (from first occurrence) or null if no occurrences
     */
    public function getPrimaryVenue(): ?\App\Models\Venue
    {
        // Ensure eventOccurrences and their venues are loaded
        if (! $this->relationLoaded('eventOccurrences')) {
            $this->load('eventOccurrences.venue');
        }

        return $this->eventOccurrences->first()?->venue;
    }

    /**
     * Get the users who have wishlisted this event.
     */
    public function wishlistedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_event_wishlists')
            ->withTimestamps();
    }

    /**
     * Check if this event is wishlisted by a specific user.
     */
    public function isWishlistedBy($user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->wishlistedByUsers()->where('user_id', $userId)->exists();
    }

    /**
     * Get the count of users who have wishlisted this event.
     */
    public function getWishlistCount(): int
    {
        return $this->wishlistedByUsers()->count();
    }

    /**
     * Get polymorphic comments for this event.
     */
    public function comments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Comment::class, 'commentable');
    }

    /**
     * Find a published event by ID or slug
     *
     * Uses database-specific JSON functions for optimal performance:
     * - MySQL: JSON_SEARCH for exact value matching
     * - PostgreSQL: JSONB operators for exact value matching
     * - SQLite: LIKE patterns with proper escaping
     *
     * @param  string|int  $identifier  Event ID or slug
     * @param  array  $with  Relationships to eager load
     * @return static|null
     */
    public static function findPublishedByIdentifier($identifier, array $with = [])
    {
        return static::with($with)
            ->where('event_status', 'published')
            ->where(function ($query) use ($identifier) {
                if (is_numeric($identifier)) {
                    $query->where('id', $identifier);
                } else {
                    // Search through all locale keys in the JSON slug
                    $query->where(function ($subQuery) use ($identifier) {
                        $availableLocales = array_keys(config('app.available_locales', ['en' => 'English']));

                        foreach ($availableLocales as $locale) {
                            $subQuery->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(`slug`, ?)) = ?', ['$."'.$locale.'"', $identifier]);
                        }

                        // Also handle legacy string slugs
                        $subQuery->orWhere('slug', $identifier);
                    });
                }
            })
            ->first();
    }

    /**
     * Check if the event is visible to a specific user based on membership levels
     */
    public function isVisibleToUser(?User $user): bool
    {
        // Public events are visible to everyone
        if ($this->isPublic()) {
            return true;
        }

        // Non-public events require authentication
        if (! $user) {
            return false;
        }

        // Check if user has required membership level
        $userMembership = $user->currentMembership;
        if (! $userMembership) {
            return false;
        }

        return in_array(
            $userMembership->membership_level_id,
            $this->visible_to_membership_levels ?? []
        );
    }

    /**
     * Check if the event is public (no membership restrictions)
     */
    public function isPublic(): bool
    {
        return empty($this->visible_to_membership_levels);
    }

    /**
     * Get the required membership levels for this event
     */
    public function getRequiredMembershipLevels()
    {
        if ($this->isPublic()) {
            return collect();
        }

        return \App\Modules\Membership\Models\MembershipLevel::whereIn('id', $this->visible_to_membership_levels)
            ->get();
    }

    /**
     * Get the names of required membership levels
     */
    public function getRequiredMembershipNames(): array
    {
        return $this->getRequiredMembershipLevels()
            ->map(function ($level) {
                // Handle translatable models - get the translation for current locale
                return $level->getTranslation('name', app()->getLocale()) ?:
                       $level->getTranslation('name', 'en') ?:
                       'Unknown Level';
            })
            ->filter()
            ->toArray();
    }

    /**
     * Get member check-ins for this event
     */
    public function memberCheckIns(): HasMany
    {
        return $this->hasMany(MemberCheckIn::class);
    }

    /**
     * Check if the event has a redirect URL configured
     */
    public function hasRedirectUrl(): bool
    {
        return ! empty($this->redirect_url);
    }

    /**
     * Get the redirect URL for this event
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirect_url;
    }

    /**
     * Get the slug for a specific locale with fallback handling
     *
     * @param  string|null  $locale  The locale to get the slug for. Defaults to current locale.
     * @return string The slug for the locale, or English slug, or event ID as fallback
     */
    public function getSlugForLocale(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        // Use getTranslations to get all locale values or try direct attribute access
        $slugs = $this->getTranslations('slug');

        // If no translations, try the direct attribute (for backward compatibility)
        if (empty($slugs)) {
            $directSlug = $this->getRawOriginal('slug');
            if ($directSlug) {
                $slugs = json_decode($directSlug, true) ?: $directSlug;
            }
        }

        // Handle case where slug is null or empty
        if (empty($slugs)) {
            return (string) $this->id;
        }

        // If slug is a string (legacy), just return it
        if (is_string($slugs)) {
            return $slugs;
        }

        // If slug is an array (translatable)
        if (is_array($slugs)) {
            // If we have the requested locale slug, return it
            if (isset($slugs[$locale]) && ! empty($slugs[$locale])) {
                return $slugs[$locale];
            }

            // Fallback to English slug
            if (isset($slugs['en']) && ! empty($slugs['en'])) {
                return $slugs['en'];
            }
        }

        // Final fallback to ID if no usable slugs available
        return (string) $this->id;
    }

    /**
     * Detect which locale a slug belongs to
     *
     * @param  string  $slug  The slug to check
     * @return string|null The locale code, or null if slug not found
     */
    public function getLocaleBySlug(string $slug): ?string
    {
        // Use getTranslations to get all locale values
        $slugs = $this->getTranslations('slug');

        // If no translations, try the direct attribute (for backward compatibility)
        if (empty($slugs)) {
            $directSlug = $this->getRawOriginal('slug');
            if ($directSlug) {
                $slugs = json_decode($directSlug, true) ?: $directSlug;
            }
        }

        // Handle case where slug is null or empty
        if (empty($slugs)) {
            return null;
        }

        // If slug is a string (legacy), check if it matches
        if (is_string($slugs)) {
            return $slugs === $slug ? 'en' : null; // Assume string slugs are English
        }

        // If slug is an array (translatable)
        if (is_array($slugs)) {
            // Search through all locale slugs
            foreach ($slugs as $locale => $localeSlug) {
                if ($localeSlug === $slug) {
                    return $locale;
                }
            }
        }

        return null;
    }

    /**
     * Get the SEO settings for this event
     */
    public function seo(): HasOne
    {
        return $this->hasOne(EventSeo::class);
    }
}
