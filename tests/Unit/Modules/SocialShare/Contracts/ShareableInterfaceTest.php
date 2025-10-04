<?php

use App\Models\Event;
use App\Modules\SocialShare\Contracts\ShareableInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ShareableInterface', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create([
            'name' => ['en' => 'Test Event', 'zh-TW' => '測試活動'],
            'description' => ['en' => 'Test Description', 'zh-TW' => '測試描述'],
            'slug' => ['en' => 'test-event', 'zh-TW' => 'test-event-tw'],
            'event_status' => 'published',
        ]);
    });

    it('defines required methods for shareable models', function () {
        expect(interface_exists(ShareableInterface::class))->toBeTrue();

        $methods = get_class_methods(ShareableInterface::class);

        expect($methods)->toContain('getShareTitle');
        expect($methods)->toContain('getShareDescription');
        expect($methods)->toContain('getShareUrl');
        expect($methods)->toContain('getShareImage');
        expect($methods)->toContain('getShareTags');
    });

    it('event model implements shareable interface', function () {
        expect($this->event)->toBeInstanceOf(ShareableInterface::class);
    });

    it('can get share title for different locales', function () {
        $titleEn = $this->event->getShareTitle('en');
        $titleZh = $this->event->getShareTitle('zh-TW');

        expect($titleEn)->toBe('Test Event');
        expect($titleZh)->toBe('測試活動');
    });

    it('can get share description for different locales', function () {
        $descEn = $this->event->getShareDescription('en');
        $descZh = $this->event->getShareDescription('zh-TW');

        expect($descEn)->toBe('Test Description');
        expect($descZh)->toBe('測試描述');
    });

    it('can get share url with locale parameter', function () {
        $url = $this->event->getShareUrl('en');

        expect($url)->toContain('test-event');
        expect($url)->toContain('/events/');
    });

    it('can get share image url', function () {
        $imageUrl = $this->event->getShareImage();

        expect($imageUrl)->toBeString();
    });

    it('can get share tags as array', function () {
        $tags = $this->event->getShareTags();

        expect($tags)->toBeArray();
    });

    it('handles fallback when locale not available', function () {
        $title = $this->event->getShareTitle('fr'); // French not available

        expect($title)->toBe('Test Event'); // Should fallback to English
    });
});
