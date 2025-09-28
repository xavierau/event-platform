<?php

use App\Models\Event;
use App\Models\EventSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Event-EventSeo Relationship', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create();
    });

    describe('one-to-one relationship', function () {
        it('event has one SEO setting', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $seo = $this->event->seo;

            expect($seo)->toBeInstanceOf(EventSeo::class);
            expect($seo->id)->toBe($eventSeo->id);
        });

        it('event seo belongs to one event', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $event = $eventSeo->event;

            expect($event)->toBeInstanceOf(Event::class);
            expect($event->id)->toBe($this->event->id);
        });

        it('enforces unique constraint on event_id', function () {
            EventSeo::factory()->forEvent($this->event)->create();

            $this->expectException(\Illuminate\Database\QueryException::class);

            // This should fail due to unique constraint
            EventSeo::factory()->forEvent($this->event)->create();
        });

        it('cascades delete from event to seo', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();
            $eventSeoId = $eventSeo->id;

            $this->event->delete();

            expect(EventSeo::find($eventSeoId))->toBeNull();
        });
    });

    describe('eager loading', function () {
        it('can eager load SEO with event', function () {
            EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'Eager Title'],
            ]);

            $eventWithSeo = Event::with('seo')->find($this->event->id);

            expect($eventWithSeo->relationLoaded('seo'))->toBeTrue();
            expect($eventWithSeo->seo->meta_title)->toBe(['en' => 'Eager Title']);
        });

        it('can eager load event with SEO', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $seoWithEvent = EventSeo::with('event')->find($eventSeo->id);

            expect($seoWithEvent->relationLoaded('event'))->toBeTrue();
            expect($seoWithEvent->event->id)->toBe($this->event->id);
        });
    });

    describe('querying', function () {
        it('can find events with SEO settings', function () {
            $eventWithSeo = Event::factory()->create();
            $eventWithoutSeo = Event::factory()->create();

            EventSeo::factory()->forEvent($eventWithSeo)->create();

            $eventsWithSeo = Event::has('seo')->get();

            expect($eventsWithSeo)->toHaveCount(1);
            expect($eventsWithSeo->first()->id)->toBe($eventWithSeo->id);
        });

        it('can find events without SEO settings', function () {
            $eventWithSeo = Event::factory()->create();
            $eventWithoutSeo = Event::factory()->create();

            EventSeo::factory()->forEvent($eventWithSeo)->create();

            $eventsWithoutSeo = Event::doesntHave('seo')->get();

            expect($eventsWithoutSeo)->toHaveCount(2); // Original event + eventWithoutSeo
            expect($eventsWithoutSeo->pluck('id'))->toContain($this->event->id);
            expect($eventsWithoutSeo->pluck('id'))->toContain($eventWithoutSeo->id);
        });

        it('can query events by SEO activity status', function () {
            $activeEvent = Event::factory()->create();
            $inactiveEvent = Event::factory()->create();

            EventSeo::factory()->forEvent($activeEvent)->create(['is_active' => true]);
            EventSeo::factory()->forEvent($inactiveEvent)->create(['is_active' => false]);

            $eventsWithActiveSeo = Event::whereHas('seo', function ($query) {
                $query->where('is_active', true);
            })->get();

            expect($eventsWithActiveSeo)->toHaveCount(1);
            expect($eventsWithActiveSeo->first()->id)->toBe($activeEvent->id);
        });

        it('can search events by SEO meta content', function () {
            $searchableEvent = Event::factory()->create();
            $otherEvent = Event::factory()->create();

            EventSeo::factory()->forEvent($searchableEvent)->create([
                'meta_title' => ['en' => 'Searchable Conference Event'],
                'keywords' => ['en' => 'conference, technology, networking'],
            ]);

            EventSeo::factory()->forEvent($otherEvent)->create([
                'meta_title' => ['en' => 'Workshop Event'],
                'keywords' => ['en' => 'workshop, learning'],
            ]);

            // Search by meta title
            $conferenceEvents = Event::whereHas('seo', function ($query) {
                $query->where('meta_title->en', 'like', '%Conference%');
            })->get();

            expect($conferenceEvents)->toHaveCount(1);
            expect($conferenceEvents->first()->id)->toBe($searchableEvent->id);

            // Search by keywords
            $techEvents = Event::whereHas('seo', function ($query) {
                $query->where('keywords->en', 'like', '%technology%');
            })->get();

            expect($techEvents)->toHaveCount(1);
            expect($techEvents->first()->id)->toBe($searchableEvent->id);
        });
    });

    describe('data integrity', function () {
        it('maintains referential integrity', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            // Verify foreign key relationship
            expect($eventSeo->event_id)->toBe($this->event->id);

            // Verify we can navigate the relationship
            expect($eventSeo->event->id)->toBe($this->event->id);
            expect($this->event->seo->id)->toBe($eventSeo->id);
        });

        it('handles soft deletes correctly', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();
            $eventSeoId = $eventSeo->id;

            // Soft delete the event
            $this->event->delete();

            // SEO should be cascade deleted (hard delete due to foreign key constraint)
            expect(EventSeo::find($eventSeoId))->toBeNull();
        });

        it('validates event existence in database constraint', function () {
            $this->expectException(\Illuminate\Database\QueryException::class);

            // Try to create SEO for non-existent event
            EventSeo::factory()->create(['event_id' => 99999]);
        });
    });

    describe('factory states', function () {
        it('creates optimized SEO settings with proper character limits', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->optimized()->create();

            // Check character limits are respected
            expect(strlen($eventSeo->meta_title['en']))->toBeLessThanOrEqual(60);
            expect(strlen($eventSeo->meta_description['en']))->toBeLessThanOrEqual(160);
            expect(strlen($eventSeo->og_title['en']))->toBeLessThanOrEqual(60);
            expect(strlen($eventSeo->og_description['en']))->toBeLessThanOrEqual(160);
            expect($eventSeo->is_active)->toBeTrue();
            expect($eventSeo->og_image_url)->not->toBeNull();
        });

        it('creates minimal SEO settings with only basic fields', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->minimal()->create();

            expect($eventSeo->meta_title)->not->toBeNull();
            expect($eventSeo->meta_description)->toBeNull();
            expect($eventSeo->keywords)->toBeNull();
            expect($eventSeo->og_title)->toBeNull();
            expect($eventSeo->og_description)->toBeNull();
            expect($eventSeo->og_image_url)->toBeNull();
        });

        it('creates English-only SEO settings', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->englishOnly()->create();

            expect($eventSeo->meta_title)->toHaveKey('en');
            expect($eventSeo->meta_title)->not->toHaveKey('zh-TW');
            expect($eventSeo->meta_description)->toHaveKey('en');
            expect($eventSeo->meta_description)->not->toHaveKey('zh-TW');
        });

        it('creates multi-locale SEO settings', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->multiLocale()->create();

            expect($eventSeo->meta_title)->toHaveKey('en');
            expect($eventSeo->meta_title)->toHaveKey('zh-TW');
            expect($eventSeo->meta_title)->toHaveKey('zh-CN');
            expect($eventSeo->meta_description)->toHaveKey('en');
            expect($eventSeo->meta_description)->toHaveKey('zh-TW');
        });

        it('creates inactive SEO settings', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->inactive()->create();

            expect($eventSeo->is_active)->toBeFalse();
        });

        it('creates SEO settings without OG image', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->withoutOgImage()->create();

            expect($eventSeo->og_image_url)->toBeNull();
        });
    });
});
