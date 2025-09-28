<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class EventSeo extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'event_id',
        'meta_title',
        'meta_description',
        'keywords',
        'og_title',
        'og_description',
        'og_image_url',
        'is_active',
    ];

    public array $translatable = [
        'meta_title',
        'meta_description',
        'keywords',
        'og_title',
        'og_description',
    ];

    protected $casts = [
        'meta_title' => 'array',
        'meta_description' => 'array',
        'keywords' => 'array',
        'og_title' => 'array',
        'og_description' => 'array',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getMetaTitleForLocale(string $locale): ?string
    {
        return $this->getTranslation('meta_title', $locale);
    }

    public function getMetaDescriptionForLocale(string $locale): ?string
    {
        return $this->getTranslation('meta_description', $locale);
    }

    public function getKeywordsForLocale(string $locale): ?string
    {
        return $this->getTranslation('keywords', $locale);
    }

    public function getOgTitleForLocale(string $locale): ?string
    {
        return $this->getTranslation('og_title', $locale);
    }

    public function getOgDescriptionForLocale(string $locale): ?string
    {
        return $this->getTranslation('og_description', $locale);
    }
}
