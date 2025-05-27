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
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'event_tag');
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
     *
     * @return string|null Formatted price range or null if no tickets available
     */
    public function getPriceRange(): ?string
    {
        // Ensure eventOccurrences and their ticketDefinitions are loaded
        if (!$this->relationLoaded('eventOccurrences')) {
            $this->load('eventOccurrences.ticketDefinitions');
        }

        // Calculate price range across all occurrences and tickets
        $allPrices = $this->eventOccurrences->flatMap(function ($occurrence) {
            return $occurrence->ticketDefinitions->map(function ($ticket) {
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
        $firstTicket = $this->eventOccurrences->flatMap->ticketDefinitions->first();
        $currencyCode = $firstTicket ? $firstTicket->currency : CurrencyHelper::getDefault();

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
     * @return static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findPublishedByIdentifier($identifier, array $with = [])
    {
        return static::with($with)
            ->where('event_status', 'published')
            ->where(function ($query) use ($identifier) {
                // Try to find by ID first (if numeric), then by slug
                if (is_numeric($identifier)) {
                    $query->where('id', $identifier);
                } else {
                    // Search in translatable slug field (JSON) - find exact match in any locale
                    // Use database-specific JSON functions for optimal performance
                    $databaseDriver = config('database.default');
                    $connectionConfig = config("database.connections.{$databaseDriver}");
                    $driver = $connectionConfig['driver'] ?? $databaseDriver;

                    $query->where(function ($subQuery) use ($identifier, $driver) {
                        switch ($driver) {
                            case 'mysql':
                                // MySQL: Use JSON_SEARCH for exact value matching
                                $subQuery->whereRaw("JSON_SEARCH(slug, 'one', ?) IS NOT NULL", [$identifier]);
                                break;

                            case 'pgsql':
                                // PostgreSQL: Use JSON operators for exact value matching
                                $subQuery->whereRaw("slug::jsonb ? ?", [$identifier])
                                    ->orWhereRaw("EXISTS (SELECT 1 FROM jsonb_each_text(slug::jsonb) WHERE value = ?)", [$identifier]);
                                break;

                            case 'sqlite':
                            default:
                                // SQLite and fallback: Use LIKE with precise patterns
                                // Use addslashes for proper escaping in LIKE queries
                                $escapedIdentifier = addslashes($identifier);
                                $subQuery->where('slug', 'LIKE', '%:"' . $escapedIdentifier . '"%')  // After colon
                                    ->orWhere('slug', 'LIKE', '%{"' . $escapedIdentifier . '"%'); // At start of object
                                break;
                        }
                    });
                }
            })
            ->firstOrFail();
    }
}
