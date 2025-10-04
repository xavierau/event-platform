<?php

use App\Models\Event;
use App\Modules\SocialShare\Contracts\ShareableInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Event ShareableInterface Implementation', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create([
            'name' => ['en' => 'Test Event', 'zh-TW' => '測試活動'],
            'description' => ['en' => 'Test Description', 'zh-TW' => '測試描述'],
            'slug' => ['en' => 'test-event', 'zh-TW' => 'test-event-tw'],
            'event_status' => 'published',
        ]);
    });

    it('implements ShareableInterface', function () {
        expect($this->event)->toBeInstanceOf(ShareableInterface::class);
    });

    it('can get share title in English', function () {
        $title = $this->event->getShareTitle('en');
        expect($title)->toBe('Test Event');
    });

    it('can get share title in Chinese', function () {
        $title = $this->event->getShareTitle('zh-TW');
        expect($title)->toBe('測試活動');
    });

    it('can get share description', function () {
        $description = $this->event->getShareDescription('en');
        expect($description)->toBe('Test Description');
    });

    it('can get share URL', function () {
        $url = $this->event->getShareUrl('en');
        expect($url)->toContain('test-event');
    });

    it('can get share image', function () {
        $image = $this->event->getShareImage();
        expect($image)->toBeString();
    });

    it('can get share tags', function () {
        $tags = $this->event->getShareTags();
        expect($tags)->toBeArray();
        expect($tags)->toContain('Event');
        expect($tags)->toContain('EventPlatform');
    });
});
