<?php

use App\Actions\EventSeo\CreateEventSeoAction;
use App\Actions\EventSeo\DeleteEventSeoAction;
use App\Actions\EventSeo\UpdateEventSeoAction;
use App\DTOs\EventSeoData;
use App\Models\Event;
use App\Models\EventSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EventSeo Actions', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create();
    });

    describe('CreateEventSeoAction', function () {
        beforeEach(function () {
            $this->action = app(CreateEventSeoAction::class);
        });

        it('creates EventSeo from EventSeoData', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Create Title'],
                'meta_description' => ['en' => 'Create Description'],
                'keywords' => ['en' => 'create, test'],
                'og_title' => ['en' => 'Create OG Title'],
                'og_description' => ['en' => 'Create OG Description'],
                'og_image_url' => 'https://example.com/create.jpg',
                'is_active' => true,
            ]);

            $eventSeo = $this->action->execute($data);

            expect($eventSeo)->toBeInstanceOf(EventSeo::class);
            expect($eventSeo->exists)->toBeTrue();
            expect($eventSeo->event_id)->toBe($this->event->id);
            expect($eventSeo->meta_title)->toBe(['en' => 'Create Title']);
            expect($eventSeo->meta_description)->toBe(['en' => 'Create Description']);
            expect($eventSeo->keywords)->toBe(['en' => 'create, test']);
            expect($eventSeo->og_title)->toBe(['en' => 'Create OG Title']);
            expect($eventSeo->og_description)->toBe(['en' => 'Create OG Description']);
            expect($eventSeo->og_image_url)->toBe('https://example.com/create.jpg');
            expect($eventSeo->is_active)->toBeTrue();
        });

        it('creates EventSeo with minimal data', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'is_active' => true,
            ]);

            $eventSeo = $this->action->execute($data);

            expect($eventSeo)->toBeInstanceOf(EventSeo::class);
            expect($eventSeo->exists)->toBeTrue();
            expect($eventSeo->event_id)->toBe($this->event->id);
            expect($eventSeo->is_active)->toBeTrue();
            expect($eventSeo->meta_title)->toBeNull();
            expect($eventSeo->og_image_url)->toBeNull();
        });

        it('creates EventSeo with multilingual content', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => 'English Title',
                    'zh-TW' => '中文標題',
                    'zh-CN' => '简体标题',
                ],
                'meta_description' => [
                    'en' => 'English Description',
                    'zh-TW' => '中文描述',
                ],
                'is_active' => true,
            ]);

            $eventSeo = $this->action->execute($data);

            expect($eventSeo->meta_title)->toBe([
                'en' => 'English Title',
                'zh-TW' => '中文標題',
                'zh-CN' => '简体标题',
            ]);
            expect($eventSeo->meta_description)->toBe([
                'en' => 'English Description',
                'zh-TW' => '中文描述',
            ]);
        });

        it('persists data to database correctly', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'DB Test Title'],
                'is_active' => true,
            ]);

            $eventSeo = $this->action->execute($data);

            $this->assertDatabaseHas('event_seos', [
                'id' => $eventSeo->id,
                'event_id' => $this->event->id,
                'is_active' => true,
            ]);

            // Check JSON fields
            $dbRecord = EventSeo::find($eventSeo->id);
            expect($dbRecord->meta_title)->toBe(['en' => 'DB Test Title']);
        });
    });

    describe('UpdateEventSeoAction', function () {
        beforeEach(function () {
            $this->action = app(UpdateEventSeoAction::class);
            $this->eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'Original Title'],
                'meta_description' => ['en' => 'Original Description'],
                'keywords' => ['en' => 'original, keywords'],
                'is_active' => true,
            ]);
        });

        it('updates EventSeo with new data', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Updated Title'],
                'meta_description' => ['en' => 'Updated Description'],
                'keywords' => ['en' => 'updated, keywords'],
                'og_title' => ['en' => 'New OG Title'],
                'is_active' => false,
            ]);

            $updatedEventSeo = $this->action->execute($this->eventSeo, $data);

            expect($updatedEventSeo->id)->toBe($this->eventSeo->id);
            expect($updatedEventSeo->meta_title)->toBe(['en' => 'Updated Title']);
            expect($updatedEventSeo->meta_description)->toBe(['en' => 'Updated Description']);
            expect($updatedEventSeo->keywords)->toBe(['en' => 'updated, keywords']);
            expect($updatedEventSeo->og_title)->toBe(['en' => 'New OG Title']);
            expect($updatedEventSeo->is_active)->toBeFalse();
        });

        it('excludes event_id from update data', function () {
            $originalEventId = $this->eventSeo->event_id;

            $data = EventSeoData::from([
                'event_id' => 999, // Different event ID that should be ignored
                'meta_title' => ['en' => 'Updated Title'],
                'is_active' => true,
            ]);

            $updatedEventSeo = $this->action->execute($this->eventSeo, $data);

            expect($updatedEventSeo->event_id)->toBe($originalEventId);
            expect($updatedEventSeo->meta_title)->toBe(['en' => 'Updated Title']);
        });

        it('returns fresh model instance', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Fresh Title'],
                'is_active' => true,
            ]);

            $updatedEventSeo = $this->action->execute($this->eventSeo, $data);

            // Verify it's a fresh instance by checking it has the updated data
            expect($updatedEventSeo->meta_title)->toBe(['en' => 'Fresh Title']);

            // Verify original instance hasn't been updated in memory
            expect($this->eventSeo->meta_title)->toBe(['en' => 'Original Title']);
        });

        it('handles partial updates correctly', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Only Title Updated'],
                'is_active' => true,
            ]);

            $updatedEventSeo = $this->action->execute($this->eventSeo, $data);

            expect($updatedEventSeo->meta_title)->toBe(['en' => 'Only Title Updated']);
            expect($updatedEventSeo->meta_description)->toBe(['en' => 'Original Description']);
            expect($updatedEventSeo->keywords)->toBe(['en' => 'original, keywords']);
        });

        it('updates multilingual content correctly', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => 'Updated English Title',
                    'zh-TW' => '更新的中文標題',
                ],
                'meta_description' => [
                    'en' => 'Updated English Description',
                    'zh-TW' => '更新的中文描述',
                ],
                'is_active' => true,
            ]);

            $updatedEventSeo = $this->action->execute($this->eventSeo, $data);

            expect($updatedEventSeo->meta_title)->toBe([
                'en' => 'Updated English Title',
                'zh-TW' => '更新的中文標題',
            ]);
            expect($updatedEventSeo->meta_description)->toBe([
                'en' => 'Updated English Description',
                'zh-TW' => '更新的中文描述',
            ]);
        });
    });

    describe('DeleteEventSeoAction', function () {
        beforeEach(function () {
            $this->action = app(DeleteEventSeoAction::class);
            $this->eventSeo = EventSeo::factory()->forEvent($this->event)->create();
        });

        it('deletes EventSeo successfully', function () {
            $eventSeoId = $this->eventSeo->id;

            $result = $this->action->execute($this->eventSeo);

            expect($result)->toBeTrue();
            expect(EventSeo::find($eventSeoId))->toBeNull();
        });

        it('removes database record completely', function () {
            $eventSeoId = $this->eventSeo->id;

            $this->action->execute($this->eventSeo);

            $this->assertDatabaseMissing('event_seos', [
                'id' => $eventSeoId,
            ]);
        });

        it('handles already deleted EventSeo gracefully', function () {
            $this->eventSeo->delete();

            // Attempting to delete an already deleted model should handle gracefully
            $result = $this->action->execute($this->eventSeo);

            expect($result)->toBeFalse();
        });
    });

    describe('integration with Event model', function () {
        it('maintains relationship integrity after actions', function () {
            // Create
            $createAction = app(CreateEventSeoAction::class);
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Integration Test'],
                'is_active' => true,
            ]);

            $eventSeo = $createAction->execute($data);

            // Verify relationship works
            expect($this->event->fresh()->seo->id)->toBe($eventSeo->id);
            expect($eventSeo->event->id)->toBe($this->event->id);

            // Update
            $updateAction = app(UpdateEventSeoAction::class);
            $updateData = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Updated Integration'],
                'is_active' => false,
            ]);

            $updatedEventSeo = $updateAction->execute($eventSeo, $updateData);
            expect($this->event->fresh()->seo->is_active)->toBeFalse();

            // Delete
            $deleteAction = app(DeleteEventSeoAction::class);
            $deleteAction->execute($updatedEventSeo);

            expect($this->event->fresh()->seo)->toBeNull();
        });
    });
});
