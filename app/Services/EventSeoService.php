<?php

namespace App\Services;

use App\Actions\EventSeo\CreateEventSeoAction;
use App\Actions\EventSeo\DeleteEventSeoAction;
use App\Actions\EventSeo\UpdateEventSeoAction;
use App\DTOs\EventSeoData;
use App\Models\Event;
use App\Models\EventSeo;

class EventSeoService
{
    public function __construct(
        private CreateEventSeoAction $createEventSeoAction,
        private UpdateEventSeoAction $updateEventSeoAction,
        private DeleteEventSeoAction $deleteEventSeoAction
    ) {}

    /**
     * Create SEO settings for an event
     */
    public function createEventSeo(EventSeoData $data): EventSeo
    {
        return $this->createEventSeoAction->execute($data);
    }

    /**
     * Update SEO settings for an event
     */
    public function updateEventSeo(EventSeo $eventSeo, EventSeoData $data): EventSeo
    {
        return $this->updateEventSeoAction->execute($eventSeo, $data);
    }

    /**
     * Delete SEO settings for an event
     */
    public function deleteEventSeo(EventSeo $eventSeo): bool
    {
        return $this->deleteEventSeoAction->execute($eventSeo);
    }

    /**
     * Get or create SEO settings for an event
     */
    public function getOrCreateEventSeo(Event $event): EventSeo
    {
        if ($event->seo) {
            return $event->seo;
        }

        // Create basic SEO settings from event data
        $seoData = EventSeoData::from([
            'event_id' => $event->id,
            'meta_title' => $event->getTranslations('name') ?: null, // Use event name as default meta title
            'meta_description' => $event->getTranslations('short_summary') ?: $event->getTranslations('description') ?: null,
            'is_active' => true,
        ]);

        return $this->createEventSeo($seoData);
    }

    /**
     * Update or create SEO settings for an event
     */
    public function upsertEventSeo(Event $event, EventSeoData $data): EventSeo
    {
        if ($event->seo) {
            return $this->updateEventSeo($event->seo, $data);
        }

        return $this->createEventSeo($data);
    }

    /**
     * Get SEO settings for an event by event ID
     */
    public function getEventSeoByEventId(int $eventId): ?EventSeo
    {
        return EventSeo::where('event_id', $eventId)->first();
    }

    /**
     * Check if event has SEO settings
     */
    public function hasEventSeo(Event $event): bool
    {
        return $event->seo !== null;
    }

    /**
     * Generate SEO meta tags for an event
     */
    public function generateMetaTags(Event $event, string $locale = 'en'): array
    {
        $seo = $event->seo;
        $tags = [];

        if ($seo && $seo->is_active) {
            // Meta title
            $metaTitle = $seo->getMetaTitleForLocale($locale) ?: $event->getTranslation('name', $locale);
            if ($metaTitle) {
                $tags['title'] = $metaTitle;
            }

            // Meta description
            $metaDescription = $seo->getMetaDescriptionForLocale($locale) ?:
                $event->getTranslation('short_summary', $locale) ?:
                $event->getTranslation('description', $locale);
            if ($metaDescription) {
                $tags['description'] = $metaDescription;
            }

            // Keywords
            $keywords = $seo->getKeywordsForLocale($locale);
            if ($keywords) {
                $tags['keywords'] = $keywords;
            }

            // Open Graph tags
            $ogTitle = $seo->getOgTitleForLocale($locale) ?: $metaTitle;
            if ($ogTitle) {
                $tags['og:title'] = $ogTitle;
            }

            $ogDescription = $seo->getOgDescriptionForLocale($locale) ?: $metaDescription;
            if ($ogDescription) {
                $tags['og:description'] = $ogDescription;
            }

            if ($seo->og_image_url) {
                $tags['og:image'] = $seo->og_image_url;
            }

            $tags['og:type'] = 'event';
        } else {
            // Fallback to event data
            $tags['title'] = $event->getTranslation('name', $locale);
            $description = $event->getTranslation('short_summary', $locale) ?:
                $event->getTranslation('description', $locale);
            if ($description) {
                $tags['description'] = $description;
                $tags['og:description'] = $description;
            }
            $tags['og:title'] = $event->getTranslation('name', $locale);
            $tags['og:type'] = 'event';
        }

        return $tags;
    }

    /**
     * Validate SEO character limits
     */
    public function validateCharacterLimits(EventSeoData $data): array
    {
        $errors = [];

        if ($data->meta_title) {
            foreach ($data->meta_title as $locale => $title) {
                if ($title && strlen($title) > 60) {
                    $errors["meta_title.{$locale}"] = "Meta title for {$locale} must not exceed 60 characters";
                }
            }
        }

        if ($data->meta_description) {
            foreach ($data->meta_description as $locale => $description) {
                if ($description && strlen($description) > 160) {
                    $errors["meta_description.{$locale}"] = "Meta description for {$locale} must not exceed 160 characters";
                }
            }
        }

        if ($data->og_title) {
            foreach ($data->og_title as $locale => $title) {
                if ($title && strlen($title) > 60) {
                    $errors["og_title.{$locale}"] = "OG title for {$locale} must not exceed 60 characters";
                }
            }
        }

        if ($data->og_description) {
            foreach ($data->og_description as $locale => $description) {
                if ($description && strlen($description) > 160) {
                    $errors["og_description.{$locale}"] = "OG description for {$locale} must not exceed 160 characters";
                }
            }
        }

        return $errors;
    }
}
