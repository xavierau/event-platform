<?php

use App\Models\Event;
use App\Models\EventSeo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EventSeoController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['created_by' => $this->user->id]);
        $this->actingAs($this->user);
    });

    describe('show', function () {
        it('displays SEO settings page', function () {
            $response = $this->get(route('admin.events.seo.show', $this->event));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('Admin/Events/Seo/Show')
                ->has('event')
                ->has('availableLocales')
            );
        });

        it('includes existing SEO data when available', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'Test Title'],
                'is_active' => true,
            ]);

            $response = $this->get(route('admin.events.seo.show', $this->event));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->has('eventSeo')
                ->where('eventSeo.meta_title.en', 'Test Title')
                ->where('eventSeo.is_active', true)
            );
        });

        it('shows null eventSeo when no SEO settings exist', function () {
            $response = $this->get(route('admin.events.seo.show', $this->event));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->where('eventSeo', null)
            );
        });
    });

    describe('edit', function () {
        it('displays SEO edit form', function () {
            $response = $this->get(route('admin.events.seo.edit', $this->event));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('Admin/Events/Seo/Edit')
                ->has('event')
                ->has('availableLocales')
            );
        });

        it('includes existing SEO data for editing', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'Edit Title'],
                'meta_description' => ['en' => 'Edit Description'],
            ]);

            $response = $this->get(route('admin.events.seo.edit', $this->event));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->has('eventSeo')
                ->where('eventSeo.meta_title.en', 'Edit Title')
                ->where('eventSeo.meta_description.en', 'Edit Description')
            );
        });
    });

    describe('store', function () {
        it('creates new SEO settings', function () {
            $data = [
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'New Title'],
                'meta_description' => ['en' => 'New Description'],
                'keywords' => ['en' => 'new, keywords'],
                'is_active' => true,
            ];

            $response = $this->post(route('admin.events.seo.store', $this->event), $data);

            $response->assertRedirect(route('admin.events.seo.show', $this->event));
            $response->assertSessionHas('success', 'SEO settings created successfully.');

            $this->assertDatabaseHas('event_seos', [
                'event_id' => $this->event->id,
                'is_active' => true,
            ]);

            $eventSeo = EventSeo::where('event_id', $this->event->id)->first();
            expect($eventSeo->meta_title)->toBe(['en' => 'New Title']);
            expect($eventSeo->meta_description)->toBe(['en' => 'New Description']);
        });

        it('validates required fields', function () {
            $response = $this->post(route('admin.events.seo.store', $this->event), []);

            $response->assertSessionHasErrors(['event_id']);
        });

        it('validates character limits', function () {
            $data = [
                'event_id' => $this->event->id,
                'meta_title' => ['en' => str_repeat('A', 61)], // Over 60 chars
                'meta_description' => ['en' => str_repeat('B', 161)], // Over 160 chars
                'is_active' => true,
            ];

            $response = $this->post(route('admin.events.seo.store', $this->event), $data);

            $response->assertSessionHasErrors(['meta_title.en', 'meta_description.en']);
        });

        it('validates URL format for og_image_url', function () {
            $data = [
                'event_id' => $this->event->id,
                'og_image_url' => 'not-a-valid-url',
                'is_active' => true,
            ];

            $response = $this->post(route('admin.events.seo.store', $this->event), $data);

            $response->assertSessionHasErrors(['og_image_url']);
        });

        it('accepts valid multilingual data', function () {
            $data = [
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => 'English Title',
                    'zh-TW' => '中文標題',
                ],
                'meta_description' => [
                    'en' => 'English Description',
                    'zh-TW' => '中文描述',
                ],
                'og_image_url' => 'https://example.com/image.jpg',
                'is_active' => true,
            ];

            $response = $this->post(route('admin.events.seo.store', $this->event), $data);

            $response->assertRedirect(route('admin.events.seo.show', $this->event));

            $eventSeo = EventSeo::where('event_id', $this->event->id)->first();
            expect($eventSeo->meta_title)->toBe([
                'en' => 'English Title',
                'zh-TW' => '中文標題',
            ]);
            expect($eventSeo->og_image_url)->toBe('https://example.com/image.jpg');
        });
    });

    describe('update', function () {
        it('updates existing SEO settings', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'Original Title'],
            ]);

            $data = [
                'meta_title' => ['en' => 'Updated Title'],
                'meta_description' => ['en' => 'Updated Description'],
                'is_active' => false,
            ];

            $response = $this->put(route('admin.events.seo.update', $this->event), $data);

            $response->assertRedirect(route('admin.events.seo.show', $this->event));
            $response->assertSessionHas('success', 'SEO settings updated successfully.');

            $eventSeo->refresh();
            expect($eventSeo->meta_title)->toBe(['en' => 'Updated Title']);
            expect($eventSeo->meta_description)->toBe(['en' => 'Updated Description']);
            expect($eventSeo->is_active)->toBeFalse();
        });

        it('creates new SEO settings if none exist', function () {
            $data = [
                'meta_title' => ['en' => 'New Title'],
                'is_active' => true,
            ];

            $response = $this->put(route('admin.events.seo.update', $this->event), $data);

            $response->assertRedirect(route('admin.events.seo.show', $this->event));

            $this->assertDatabaseHas('event_seos', [
                'event_id' => $this->event->id,
            ]);
        });

        it('validates character limits on update', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $data = [
                'meta_title' => ['en' => str_repeat('A', 61)],
                'is_active' => true,
            ];

            $response = $this->put(route('admin.events.seo.update', $this->event), $data);

            $response->assertSessionHasErrors(['meta_title.en']);
        });
    });

    describe('destroy', function () {
        it('removes SEO settings', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create();

            $response = $this->delete(route('admin.events.seo.destroy', $this->event));

            $response->assertRedirect(route('admin.events.show', $this->event));
            $response->assertSessionHas('success', 'SEO settings removed successfully.');

            $this->assertDatabaseMissing('event_seos', [
                'id' => $eventSeo->id,
            ]);
        });

        it('handles request when no SEO settings exist', function () {
            $response = $this->delete(route('admin.events.seo.destroy', $this->event));

            $response->assertRedirect(route('admin.events.show', $this->event));
            $response->assertSessionHas('success', 'SEO settings removed successfully.');
        });
    });

    describe('getSeoData', function () {
        it('returns SEO data as JSON', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'API Title'],
                'is_active' => true,
            ]);

            $response = $this->get(route('admin.events.seo.data', $this->event));

            $response->assertSuccessful();
            $response->assertJson([
                'eventSeo' => [
                    'event_id' => $this->event->id,
                    'meta_title' => ['en' => 'API Title'],
                    'is_active' => true,
                ],
            ]);
        });

        it('returns null when no SEO settings exist', function () {
            $response = $this->get(route('admin.events.seo.data', $this->event));

            $response->assertSuccessful();
            $response->assertJson([
                'eventSeo' => null,
            ]);
        });
    });

    describe('preview', function () {
        it('generates meta tags preview', function () {
            EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'Preview Title'],
                'meta_description' => ['en' => 'Preview Description'],
                'is_active' => true,
            ]);

            $response = $this->get(route('admin.events.seo.preview', $this->event));

            $response->assertSuccessful();
            $response->assertJson([
                'metaTags' => [
                    'title' => 'Preview Title',
                    'description' => 'Preview Description',
                    'og:type' => 'event',
                ],
                'locale' => 'en',
            ]);
        });

        it('accepts locale parameter', function () {
            EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => [
                    'en' => 'English Title',
                    'zh-TW' => '中文標題',
                ],
                'is_active' => true,
            ]);

            $response = $this->get(route('admin.events.seo.preview', $this->event).'?locale=zh-TW');

            $response->assertSuccessful();
            $response->assertJson([
                'metaTags' => [
                    'title' => '中文標題',
                ],
                'locale' => 'zh-TW',
            ]);
        });
    });

    describe('validateLimits', function () {
        it('validates character limits and returns results', function () {
            $data = [
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Valid title'],
                'meta_description' => ['en' => 'Valid description'],
                'is_active' => true,
            ];

            $response = $this->post(route('admin.events.seo.validate'), $data);

            $response->assertSuccessful();
            $response->assertJson([
                'valid' => true,
                'errors' => [],
            ]);
        });

        it('returns validation errors for character limit violations', function () {
            $data = [
                'event_id' => $this->event->id,
                'meta_title' => ['en' => str_repeat('A', 61)],
                'is_active' => true,
            ];

            $response = $this->post(route('admin.events.seo.validate'), $data);

            $response->assertSuccessful();
            $response->assertJson([
                'valid' => false,
            ]);
            $response->assertJsonPath('errors.meta_title.en', function ($error) {
                return str_contains($error, '60 characters');
            });
        });
    });

    describe('authorization', function () {
        it('denies access to unauthorized users', function () {
            $otherUser = User::factory()->create();
            $otherEvent = Event::factory()->create(['created_by' => $otherUser->id]);

            $response = $this->get(route('admin.events.seo.show', $otherEvent));

            $response->assertForbidden();
        });

        it('allows access to event creators', function () {
            $response = $this->get(route('admin.events.seo.show', $this->event));

            $response->assertSuccessful();
        });
    });
});
