<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Organizer;

class Venue extends Model implements HasMedia
{
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;

    public array $translatable = [
        'name',
        'description',
        'address_line_1',
        'address_line_2',
        'city',
    ];

    protected $fillable = [
        'name',
        'description',
        'slug',
        'organizer_id',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'state_id',
        'country_id',
        'latitude',
        'longitude',
        'contact_email',
        'contact_phone',
        'website_url',
        'seating_capacity',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'address_line_1' => 'array',
        'address_line_2' => 'array',
        'city' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'seating_capacity' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile();

        $this->addMediaCollection('gallery');

        $this->addMediaCollection('floor_plan')
            ->singleFile();

        $this->addMediaCollection('menu_pdf')
            ->singleFile();
    }

    public function registerMediaConversions(\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        if ($media && in_array($media->collection_name, ['featured_image', 'gallery', 'floor_plan'])) {
            $this->addMediaConversion('thumbnail')
                ->width(200)
                ->height(200)
                ->keepOriginalImageFormat()
                ->sharpen(5)
                ->nonQueued();

            $this->addMediaConversion('preview')
                ->width(400)
                ->height(400)
                ->keepOriginalImageFormat()
                ->sharpen(5)
                ->nonQueued();
        }
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getAddressAttribute(): string
    {
        return $this->address_line_1 . ' ' . $this->address_line_2 . ' ' . $this->city . ' ' . $this->postal_code . ' ';
    }

    // Helper methods for dual ownership as per ORG-004 requirements

    /**
     * Check if this venue is public (not owned by any organizer).
     */
    public function isPublic(): bool
    {
        return is_null($this->organizer_id);
    }

    /**
     * Check if this venue is owned by a specific organizer.
     */
    public function isOwnedBy($organizer): bool
    {
        if (is_null($this->organizer_id)) {
            return false;
        }

        $organizerId = is_object($organizer) ? $organizer->id : $organizer;
        return $this->organizer_id === $organizerId;
    }

    /**
     * Check if this venue is organizer-specific (not public).
     */
    public function isOrganizerSpecific(): bool
    {
        return !$this->isPublic();
    }
}
