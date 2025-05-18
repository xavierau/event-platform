<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
}
