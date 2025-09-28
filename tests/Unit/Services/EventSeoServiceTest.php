<?php

use App\DTOs\EventSeoData;
use App\Models\Event;
use App\Models\EventSeo;
use App\Services\EventSeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EventSeoService', function () {
    beforeEach(function () {
        $this->eventSeoService = app(EventSeoService::class);
        $this->event = Event::factory()->create();
    });

    describe('createEventSeo', function () {
        it('can create SEO settings for an event', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Test Event'],
                'meta_description' => ['en' => 'Test Description'],
                'keywords' => ['en' => 'test, event'],
                'is_active' => true,
            ]);

            $eventSeo = $this->eventSeoService->createEventSeo($data);

            expect($eventSeo)->toBeInstanceOf(EventSeo::class);
            expect($eventSeo->event_id)->toBe($this->event->id);
            expect($eventSeo->getTranslations('meta_title'))->toBe(['en' => 'Test Event']);
            expect($eventSeo->is_active)->toBeTrue();
        });

        it('creates SEO settings with all multilingual fields', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => 'English Title',
                    'zh-TW' => '中文標題',
                ],
                'meta_description' => [
                    'en' => 'English Description',
                    'zh-TW' => '中文描述',
                ],
                'keywords' => [
                    'en' => 'english, keywords',
                    'zh-TW' => '中文, 關鍵字',
                ],
                'og_title' => [
                    'en' => 'OG English',
                    'zh-TW' => 'OG 中文',
                ],
                'og_description' => [
                    'en' => 'OG English Desc',
                    'zh-TW' => 'OG 中文描述',
                ],
                'og_image_url' => 'https://example.com/image.jpg',
                'is_active' => true,
            ]);

            $eventSeo = $this->eventSeoService->createEventSeo($data);

            expect($eventSeo->getTranslations('meta_title'))->toBe([
                'en' => 'English Title',
                'zh-TW' => '中文標題',
            ]);
            expect($eventSeo->og_image_url)->toBe('https://example.com/image.jpg');
        });
    });

    describe('updateEventSeo', function () {
        it('can update existing SEO settings', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Updated Title'],
                'meta_description' => ['en' => 'Updated Description'],
                'is_active' => false,
            ]);

            $updatedEventSeo = $this->eventSeoService->updateEventSeo($eventSeo, $data);

            expect($updatedEventSeo->getTranslations('meta_title'))->toBe(['en' => 'Updated Title']);
            expect($updatedEventSeo->getTranslations('meta_description'))->toBe(['en' => 'Updated Description']);
            expect($updatedEventSeo->is_active)->toBeFalse();
        });

        it('preserves unchanged fields when updating', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'og_image_url' => 'https://original.com/image.jpg',
                'keywords' => ['en' => 'original, keywords'],
            ]);

            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Updated Title'],
                'keywords' => $eventSeo->keywords,
                'og_image_url' => $eventSeo->og_image_url,
                'is_active' => true,
            ]);

            $updatedEventSeo = $this->eventSeoService->updateEventSeo($eventSeo, $data);

            expect($updatedEventSeo->og_image_url)->toBe('https://original.com/image.jpg');
            expect($updatedEventSeo->getTranslations('keywords'))->toBe(['en' => 'original, keywords']);
        });
    });

    describe('deleteEventSeo', function () {
        it('can delete SEO settings', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $result = $this->eventSeoService->deleteEventSeo($eventSeo);

            expect($result)->toBeTrue();
            expect(EventSeo::find($eventSeo->id))->toBeNull();
        });
    });

    describe('getOrCreateEventSeo', function () {
        it('returns existing SEO settings if they exist', function () {
            $existingSeo = EventSeo::factory()->forEvent($this->event)->create();

            $result = $this->eventSeoService->getOrCreateEventSeo($this->event);

            expect($result->id)->toBe($existingSeo->id);
        });

        it('creates new SEO settings from event data if none exist', function () {
            $this->event->update([
                'name' => ['en' => 'Test Event Name'],
                'short_summary' => ['en' => 'Test summary'],
            ]);

            $result = $this->eventSeoService->getOrCreateEventSeo($this->event->fresh());

            expect($result)->toBeInstanceOf(EventSeo::class);
            expect($result->event_id)->toBe($this->event->id);
            expect($result->getTranslations('meta_title'))->toBe(['en' => 'Test Event Name']);
            expect($result->getTranslations('meta_description'))->toBe(['en' => 'Test summary']);
            expect($result->is_active)->toBeTrue();
        });
    });

    describe('upsertEventSeo', function () {
        it('creates new SEO settings if none exist', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'New Title'],
                'is_active' => true,
            ]);

            $result = $this->eventSeoService->upsertEventSeo($this->event, $data);

            expect($result)->toBeInstanceOf(EventSeo::class);
            expect($result->getTranslations('meta_title'))->toBe(['en' => 'New Title']);
        });

        it('updates existing SEO settings if they exist', function () {
            $existingSeo = EventSeo::factory()->forEvent($this->event)->create();

            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Updated Title'],
                'is_active' => true,
            ]);

            $result = $this->eventSeoService->upsertEventSeo($this->event, $data);

            expect($result->id)->toBe($existingSeo->id);
            expect($result->getTranslations('meta_title'))->toBe(['en' => 'Updated Title']);
        });
    });

    describe('generateMetaTags', function () {
        it('generates meta tags from SEO settings when active', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'SEO Title'],
                'meta_description' => ['en' => 'SEO Description'],
                'keywords' => ['en' => 'seo, keywords'],
                'og_title' => ['en' => 'OG Title'],
                'og_description' => ['en' => 'OG Description'],
                'og_image_url' => 'https://example.com/image.jpg',
                'is_active' => true,
            ]);

            $tags = $this->eventSeoService->generateMetaTags($this->event, 'en');

            expect($tags)->toBe([
                'title' => 'SEO Title',
                'description' => 'SEO Description',
                'keywords' => 'seo, keywords',
                'og:title' => 'OG Title',
                'og:description' => 'OG Description',
                'og:image' => 'https://example.com/image.jpg',
                'og:type' => 'event',
            ]);
        });

        it('falls back to event data when SEO settings are inactive', function () {
            $this->event->update([
                'name' => ['en' => 'Event Name'],
                'short_summary' => ['en' => 'Event Summary'],
            ]);

            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'is_active' => false,
            ]);

            $tags = $this->eventSeoService->generateMetaTags($this->event, 'en');

            expect($tags['title'])->toBe('Event Name');
            expect($tags['description'])->toBe('Event Summary');
            expect($tags['og:title'])->toBe('Event Name');
            expect($tags['og:type'])->toBe('event');
        });

        it('falls back to event data when no SEO settings exist', function () {
            $this->event->update([
                'name' => ['en' => 'Event Name'],
                'description' => ['en' => 'Event Description'],
            ]);

            $tags = $this->eventSeoService->generateMetaTags($this->event->fresh(), 'en');

            expect($tags['title'])->toBe('Event Name');
            expect($tags['description'])->toBe('Event Description');
            expect($tags['og:title'])->toBe('Event Name');
            expect($tags['og:type'])->toBe('event');
        });
    });

    describe('validateCharacterLimits', function () {
        it('returns no errors for valid character limits', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Short title'],
                'meta_description' => ['en' => 'Short description'],
                'og_title' => ['en' => 'Short OG title'],
                'og_description' => ['en' => 'Short OG description'],
                'is_active' => true,
            ]);

            $errors = $this->eventSeoService->validateCharacterLimits($data);

            expect($errors)->toBeEmpty();
        });

        it('returns errors for meta title exceeding limit', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => str_repeat('A', 61)], // 61 characters
                'is_active' => true,
            ]);

            $errors = $this->eventSeoService->validateCharacterLimits($data);

            expect($errors)->toHaveKey('meta_title.en');
            expect($errors['meta_title.en'])->toContain('60 characters');
        });

        it('returns errors for meta description exceeding limit', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_description' => ['en' => str_repeat('A', 161)], // 161 characters
                'is_active' => true,
            ]);

            $errors = $this->eventSeoService->validateCharacterLimits($data);

            expect($errors)->toHaveKey('meta_description.en');
            expect($errors['meta_description.en'])->toContain('160 characters');
        });

        it('returns errors for multiple locales and fields', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => str_repeat('A', 61),
                    'zh-TW' => str_repeat('B', 61),
                ],
                'og_title' => [
                    'en' => str_repeat('C', 61),
                ],
                'is_active' => true,
            ]);

            $errors = $this->eventSeoService->validateCharacterLimits($data);

            expect($errors)->toHaveKeys(['meta_title.en', 'meta_title.zh-TW', 'og_title.en']);
        });
    });

    describe('getEventSeoByEventId', function () {
        it('returns SEO settings for existing event', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $result = $this->eventSeoService->getEventSeoByEventId($this->event->id);

            expect($result->id)->toBe($eventSeo->id);
        });

        it('returns null for event without SEO settings', function () {
            $result = $this->eventSeoService->getEventSeoByEventId($this->event->id);

            expect($result)->toBeNull();
        });
    });

    describe('hasEventSeo', function () {
        it('returns true when event has SEO settings', function () {
            EventSeo::factory()->forEvent($this->event)->create();

            $result = $this->eventSeoService->hasEventSeo($this->event->fresh());

            expect($result)->toBeTrue();
        });

        it('returns false when event has no SEO settings', function () {
            $result = $this->eventSeoService->hasEventSeo($this->event);

            expect($result)->toBeFalse();
        });
    });
});
