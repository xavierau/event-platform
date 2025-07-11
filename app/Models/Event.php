<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Helpers\CurrencyHelper;
use App\Models\User;
use App\Models\Organizer;
use App\Models\Category;
use App\Models\EventOccurrence;
use App\Models\Tag;
use App\Models\Venue;
use App\Enums\CommentConfigEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia;

    public const EVENT_STATUSES = [
        'draft',
        'pending_approval',
        'published',
        'cancelled',
        'completed',
        'past'
    ];

    public const VISIBILITIES = [
        'public',
        'private',
        'unlisted'
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
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
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

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
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
        return !is_null($this->organizer_id);
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
        if (!$this->organizer) {
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

    public function registerMediaConversions(Media $media = null): void
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
     * @return string|null Formatted price range or null if no tickets available
     */
    public function getPriceRange(): ?string
    {
        // Ensure eventOccurrences and their ticketDefinitions are loaded
        if (!$this->relationLoaded('eventOccurrences')) {
            $this->load('eventOccurrences.ticketDefinitions');
        }

        $nowUtc = now()->utc();

        // Calculate price range across all occurrences and tickets
        // Only include tickets that are currently available
        $allPrices = $this->eventOccurrences->flatMap(function ($occurrence) use ($nowUtc) {
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
            })->map(function ($ticket) {
                // Use price_override if available, otherwise use original price
                return $ticket->pivot->price_override ?? $ticket->price;
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
        if (!$this->relationLoaded('eventOccurrences')) {
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
     * Find a published event by ID or slug
     *
     * Uses database-specific JSON functions for optimal performance:
     * - MySQL: JSON_SEARCH for exact value matching
     * - PostgreSQL: JSONB operators for exact value matching
     * - SQLite: LIKE patterns with proper escaping
     *
     * @param string|int $identifier Event ID or slug
     * @param array $with Relationships to eager load
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
                    $query->whereJsonContains('slug', $identifier);
                }
            })
            ->first();
    }
}
