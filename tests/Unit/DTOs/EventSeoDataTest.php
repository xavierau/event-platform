<?php

use App\DTOs\EventSeoData;
use App\Models\Event;
use App\Models\EventSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EventSeoData', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create();
    });

    describe('creation and validation', function () {
        it('can be created with valid data', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => ['en' => 'Test Title'],
                'meta_description' => ['en' => 'Test Description'],
                'keywords' => ['en' => 'test, keywords'],
                'og_title' => ['en' => 'OG Title'],
                'og_description' => ['en' => 'OG Description'],
                'og_image_url' => 'https://example.com/image.jpg',
                'is_active' => true,
            ]);

            expect($data)->toBeInstanceOf(EventSeoData::class);
            expect($data->event_id)->toBe($this->event->id);
            expect($data->meta_title)->toBe(['en' => 'Test Title']);
            expect($data->og_image_url)->toBe('https://example.com/image.jpg');
            expect($data->is_active)->toBeTrue();
        });

        it('can be created with minimal data', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'is_active' => true,
            ]);

            expect($data->event_id)->toBe($this->event->id);
            expect($data->meta_title)->toBeNull();
            expect($data->meta_description)->toBeNull();
            expect($data->keywords)->toBeNull();
            expect($data->og_title)->toBeNull();
            expect($data->og_description)->toBeNull();
            expect($data->og_image_url)->toBeNull();
            expect($data->is_active)->toBeTrue();
        });

        it('handles multilingual data correctly', function () {
            $multilingualData = [
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => 'English Title',
                    'zh-TW' => '中文標題',
                    'zh-CN' => '简体中文标题',
                ],
                'meta_description' => [
                    'en' => 'English Description',
                    'zh-TW' => '中文描述',
                ],
                'is_active' => true,
            ];

            $data = EventSeoData::from($multilingualData);

            expect($data->meta_title)->toBe([
                'en' => 'English Title',
                'zh-TW' => '中文標題',
                'zh-CN' => '简体中文标题',
            ]);
            expect($data->meta_description)->toBe([
                'en' => 'English Description',
                'zh-TW' => '中文描述',
            ]);
        });
    });

    describe('fromModel', function () {
        it('creates DTO from EventSeo model', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => ['en' => 'Model Title'],
                'meta_description' => ['en' => 'Model Description'],
                'keywords' => ['en' => 'model, keywords'],
                'og_title' => ['en' => 'Model OG Title'],
                'og_description' => ['en' => 'Model OG Description'],
                'og_image_url' => 'https://example.com/model.jpg',
                'is_active' => false,
            ]);

            $data = EventSeoData::fromModel($eventSeo);

            expect($data)->toBeInstanceOf(EventSeoData::class);
            expect($data->event_id)->toBe($eventSeo->event_id);
            expect($data->meta_title)->toBe(['en' => 'Model Title']);
            expect($data->meta_description)->toBe(['en' => 'Model Description']);
            expect($data->keywords)->toBe(['en' => 'model, keywords']);
            expect($data->og_title)->toBe(['en' => 'Model OG Title']);
            expect($data->og_description)->toBe(['en' => 'Model OG Description']);
            expect($data->og_image_url)->toBe('https://example.com/model.jpg');
            expect($data->is_active)->toBeFalse();
        });

        it('handles null values from model', function () {
            $eventSeo = EventSeo::factory()->forEvent($this->event)->create([
                'meta_title' => null,
                'meta_description' => null,
                'keywords' => null,
                'og_title' => null,
                'og_description' => null,
                'og_image_url' => null,
                'is_active' => true,
            ]);

            $data = EventSeoData::fromModel($eventSeo);

            expect($data->meta_title)->toBeNull();
            expect($data->meta_description)->toBeNull();
            expect($data->keywords)->toBeNull();
            expect($data->og_title)->toBeNull();
            expect($data->og_description)->toBeNull();
            expect($data->og_image_url)->toBeNull();
            expect($data->is_active)->toBeTrue();
        });
    });

    describe('locale-specific getters', function () {
        it('gets meta title for specific locale', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => 'English Title',
                    'zh-TW' => '中文標題',
                ],
                'is_active' => true,
            ]);

            expect($data->getMetaTitleForLocale('en'))->toBe('English Title');
            expect($data->getMetaTitleForLocale('zh-TW'))->toBe('中文標題');
            expect($data->getMetaTitleForLocale('fr'))->toBeNull();
        });

        it('gets meta description for specific locale', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_description' => [
                    'en' => 'English Description',
                    'zh-TW' => '中文描述',
                ],
                'is_active' => true,
            ]);

            expect($data->getMetaDescriptionForLocale('en'))->toBe('English Description');
            expect($data->getMetaDescriptionForLocale('zh-TW'))->toBe('中文描述');
            expect($data->getMetaDescriptionForLocale('de'))->toBeNull();
        });

        it('gets keywords for specific locale', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'keywords' => [
                    'en' => 'english, keywords',
                    'zh-TW' => '中文, 關鍵字',
                ],
                'is_active' => true,
            ]);

            expect($data->getKeywordsForLocale('en'))->toBe('english, keywords');
            expect($data->getKeywordsForLocale('zh-TW'))->toBe('中文, 關鍵字');
            expect($data->getKeywordsForLocale('es'))->toBeNull();
        });

        it('gets OG title for specific locale', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'og_title' => [
                    'en' => 'English OG Title',
                    'zh-TW' => '中文 OG 標題',
                ],
                'is_active' => true,
            ]);

            expect($data->getOgTitleForLocale('en'))->toBe('English OG Title');
            expect($data->getOgTitleForLocale('zh-TW'))->toBe('中文 OG 標題');
            expect($data->getOgTitleForLocale('pt'))->toBeNull();
        });

        it('gets OG description for specific locale', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'og_description' => [
                    'en' => 'English OG Description',
                    'zh-TW' => '中文 OG 描述',
                ],
                'is_active' => true,
            ]);

            expect($data->getOgDescriptionForLocale('en'))->toBe('English OG Description');
            expect($data->getOgDescriptionForLocale('zh-TW'))->toBe('中文 OG 描述');
            expect($data->getOgDescriptionForLocale('ru'))->toBeNull();
        });

        it('handles null translatable fields gracefully', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => null,
                'meta_description' => null,
                'keywords' => null,
                'og_title' => null,
                'og_description' => null,
                'is_active' => true,
            ]);

            expect($data->getMetaTitleForLocale('en'))->toBeNull();
            expect($data->getMetaDescriptionForLocale('en'))->toBeNull();
            expect($data->getKeywordsForLocale('en'))->toBeNull();
            expect($data->getOgTitleForLocale('en'))->toBeNull();
            expect($data->getOgDescriptionForLocale('en'))->toBeNull();
        });
    });

    describe('validation rules', function () {
        it('defines proper validation rules', function () {
            $rules = EventSeoData::rules();

            expect($rules)->toHaveKey('event_id');
            expect($rules)->toHaveKey('meta_title');
            expect($rules)->toHaveKey('meta_title.*');
            expect($rules)->toHaveKey('meta_description');
            expect($rules)->toHaveKey('meta_description.*');
            expect($rules)->toHaveKey('keywords');
            expect($rules)->toHaveKey('keywords.*');
            expect($rules)->toHaveKey('og_title');
            expect($rules)->toHaveKey('og_title.*');
            expect($rules)->toHaveKey('og_description');
            expect($rules)->toHaveKey('og_description.*');
            expect($rules)->toHaveKey('og_image_url');
            expect($rules)->toHaveKey('is_active');
        });

        it('enforces character limits in validation rules', function () {
            $rules = EventSeoData::rules();

            expect($rules['meta_title.*'])->toContain('max:60');
            expect($rules['meta_description.*'])->toContain('max:160');
            expect($rules['keywords.*'])->toContain('max:255');
            expect($rules['og_title.*'])->toContain('max:60');
            expect($rules['og_description.*'])->toContain('max:160');
            expect($rules['og_image_url'])->toContain('url');
            expect($rules['og_image_url'])->toContain('max:255');
        });

        it('requires event_id and validates its existence', function () {
            $rules = EventSeoData::rules();

            expect($rules['event_id'])->toContain('required');
            expect($rules['event_id'])->toContain('integer');
            expect($rules['event_id'])->toContain('exists:events,id');
        });
    });

    describe('edge cases', function () {
        it('handles empty arrays for translatable fields', function () {
            $data = EventSeoData::from([
                'event_id' => $this->event->id,
                'meta_title' => [],
                'meta_description' => [],
                'keywords' => [],
                'og_title' => [],
                'og_description' => [],
                'is_active' => true,
            ]);

            expect($data->meta_title)->toBe([]);
            expect($data->getMetaTitleForLocale('en'))->toBeNull();
        });

        it('preserves exact structure for mixed locale data', function () {
            $complexData = [
                'event_id' => $this->event->id,
                'meta_title' => [
                    'en' => 'English',
                    'zh-TW' => '繁體中文',
                    'zh-CN' => '简体中文',
                    'ja' => '日本語',
                ],
                'meta_description' => [
                    'en' => 'English desc',
                    'zh-TW' => '繁體描述',
                ],
                'keywords' => [
                    'en' => 'one, two, three',
                ],
                'is_active' => true,
            ];

            $data = EventSeoData::from($complexData);

            expect($data->meta_title)->toHaveCount(4);
            expect($data->meta_description)->toHaveCount(2);
            expect($data->keywords)->toHaveCount(1);
            expect($data->getMetaTitleForLocale('ja'))->toBe('日本語');
        });
    });
});
