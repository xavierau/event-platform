<?php

use App\Models\Event;
use App\Models\User;
use App\Modules\SocialShare\Actions\GenerateShareUrlAction;
use App\Modules\SocialShare\Actions\TrackShareAction;
use App\Modules\SocialShare\Models\SocialShareAnalytic;
use App\Modules\SocialShare\Services\SocialShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('SocialShareService', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create([
            'name' => ['en' => 'Test Event', 'zh-TW' => '測試活動'],
            'description' => ['en' => 'Test Description', 'zh-TW' => '測試描述'],
            'slug' => ['en' => 'test-event', 'zh-TW' => 'test-event-tw'],
            'event_status' => 'published',
        ]);

        $this->user = User::factory()->create();

        $this->generateAction = app(GenerateShareUrlAction::class);
        $this->trackAction = app(TrackShareAction::class);
        $this->service = app(SocialShareService::class);
    });

    describe('share URL generation', function () {
        it('generates share URLs for all enabled platforms', function () {
            $shareUrls = $this->service->generateShareUrls($this->event, null, 'en');

            expect($shareUrls)->toBeArray();
            expect($shareUrls)->toHaveKey('facebook');
            expect($shareUrls)->toHaveKey('twitter');
            expect($shareUrls)->toHaveKey('linkedin');
            expect($shareUrls['facebook'])->toContain('facebook.com');
            expect($shareUrls['twitter'])->toContain('twitter.com');
        });

        it('generates share URLs for specific platforms only', function () {
            $platforms = ['facebook', 'twitter'];
            $shareUrls = $this->service->generateShareUrls($this->event, $platforms, 'en');

            expect($shareUrls)->toHaveCount(2);
            expect($shareUrls)->toHaveKey('facebook');
            expect($shareUrls)->toHaveKey('twitter');
            expect($shareUrls)->not()->toHaveKey('linkedin');
        });

        it('supports multilingual URL generation', function () {
            $shareUrlsEn = $this->service->generateShareUrls($this->event, ['facebook'], 'en');
            $shareUrlsZh = $this->service->generateShareUrls($this->event, ['facebook'], 'zh-TW');

            expect($shareUrlsEn['facebook'])->toContain(urlencode('Test Event'));
            expect($shareUrlsZh['facebook'])->toContain(urlencode('測試活動'));
        });

        it('caches share URLs when caching is enabled', function () {
            config(['social-share.cache.enabled' => true]);
            config(['social-share.cache.ttl' => 300]);

            $shareUrls1 = $this->service->generateShareUrls($this->event, ['facebook'], 'en');
            $shareUrls2 = $this->service->generateShareUrls($this->event, ['facebook'], 'en');

            expect($shareUrls1)->toBe($shareUrls2);

            // Verify cache was used
            $cacheKey = $this->service->getCacheKey($this->event, ['facebook'], 'en');
            expect(Cache::has($cacheKey))->toBeTrue();
        });

        it('skips caching when disabled', function () {
            config(['social-share.cache.enabled' => false]);

            $cacheKey = $this->service->getCacheKey($this->event, ['facebook'], 'en');
            Cache::put($cacheKey, ['fake' => 'data'], 300);

            $shareUrls = $this->service->generateShareUrls($this->event, ['facebook'], 'en');

            expect($shareUrls)->not()->toBe(['fake' => 'data']);
            expect($shareUrls['facebook'])->toContain('facebook.com');
        });
    });

    describe('share tracking', function () {
        it('tracks share actions', function () {
            $analytic = $this->service->trackShare(
                $this->event,
                'facebook',
                $this->user,
                '192.168.1.1',
                'Mozilla/5.0 (Test)',
                'https://example.com'
            );

            expect($analytic)->toBeInstanceOf(SocialShareAnalytic::class);
            expect($analytic->shareable_id)->toBe($this->event->id);
            expect($analytic->platform)->toBe('facebook');
            expect($analytic->user_id)->toBe($this->user->id);

            $this->assertDatabaseHas('social_share_analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
            ]);
        });

        it('tracks anonymous share actions', function () {
            $analytic = $this->service->trackShare(
                $this->event,
                'twitter',
                null,
                '192.168.1.1'
            );

            expect($analytic->user_id)->toBeNull();
            expect($analytic->platform)->toBe('twitter');
        });

        it('tracks share from HTTP request', function () {
            $request = Request::create('/api/share-track', 'POST', [
                'platform' => 'facebook',
                'metadata' => ['source' => 'widget'],
            ], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test)',
                'HTTP_REFERER' => 'https://example.com',
                'REMOTE_ADDR' => '192.168.1.1',
            ]);

            $analytic = $this->service->trackShareFromRequest($this->event, $request, $this->user);

            expect($analytic->platform)->toBe('facebook');
            expect($analytic->user_id)->toBe($this->user->id);
            expect($analytic->metadata)->toBe(['source' => 'widget']);
        });
    });

    describe('analytics and statistics', function () {
        beforeEach(function () {
            // Create test data
            SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.1',
                'created_at' => now()->subDays(1),
            ]);

            SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
                'ip_address' => '192.168.1.2',
                'created_at' => now()->subDays(2),
            ]);

            SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.3',
                'created_at' => now()->subDays(3),
            ]);
        });

        it('gets total share count for model', function () {
            $count = $this->service->getShareCount($this->event);

            expect($count)->toBe(3);
        });

        it('gets share count by platform', function () {
            $counts = $this->service->getShareCountByPlatform($this->event);

            expect($counts)->toBe([
                'facebook' => 2,
                'twitter' => 1,
            ]);
        });

        it('gets share statistics with filters', function () {
            $stats = $this->service->getShareStatistics($this->event, [
                'date_from' => now()->subDays(2),
                'date_to' => now(),
            ]);

            expect($stats['total_shares'])->toBe(2);
            expect($stats['shares_by_platform']['facebook'])->toBe(1);
            expect($stats['shares_by_platform']['twitter'])->toBe(1);
        });

        it('gets popular content by shares', function () {
            $popular = $this->service->getPopularContent('App\\Models\\Event', 5);

            expect($popular)->toHaveCount(1);
            expect($popular->first()->shareable_id)->toBe($this->event->id);
            expect($popular->first()->share_count)->toBe(3);
        });
    });

    describe('platform configuration', function () {
        it('gets enabled platforms', function () {
            $platforms = $this->service->getEnabledPlatforms();

            expect($platforms)->toBeArray();
            expect($platforms)->toContain('facebook');
            expect($platforms)->toContain('twitter');
            expect($platforms)->toContain('linkedin');
        });

        it('gets platform configuration', function () {
            $config = $this->service->getPlatformConfig('facebook');

            expect($config)->toBeArray();
            expect($config)->toHaveKey('name');
            expect($config)->toHaveKey('icon');
            expect($config)->toHaveKey('color');
            expect($config['name'])->toBe('Facebook');
        });

        it('returns null for invalid platform', function () {
            $config = $this->service->getPlatformConfig('invalid');

            expect($config)->toBeNull();
        });

        it('checks if platform is enabled', function () {
            expect($this->service->isPlatformEnabled('facebook'))->toBeTrue();
            expect($this->service->isPlatformEnabled('invalid'))->toBeFalse();
        });
    });

    describe('UI helpers', function () {
        it('determines UI mode based on platform count', function () {
            $fewPlatforms = ['facebook', 'twitter'];
            $manyPlatforms = ['facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram'];

            config(['social-share.ui.max_buttons_before_dropdown' => 4]);

            expect($this->service->getUIMode($fewPlatforms))->toBe('buttons');
            expect($this->service->getUIMode($manyPlatforms))->toBe('dropdown');
        });

        it('gets UI configuration', function () {
            $uiConfig = $this->service->getUIConfig();

            expect($uiConfig)->toBeArray();
            expect($uiConfig)->toHaveKey('button_style');
            expect($uiConfig)->toHaveKey('show_count');
            expect($uiConfig)->toHaveKey('show_labels');
        });

        it('gets share button data for frontend', function () {
            $buttonData = $this->service->getShareButtonData($this->event, ['facebook', 'twitter'], 'en');

            expect($buttonData)->toBeArray();
            expect($buttonData)->toHaveKey('platforms');
            expect($buttonData)->toHaveKey('share_urls');
            expect($buttonData)->toHaveKey('share_counts');
            expect($buttonData)->toHaveKey('ui_config');

            expect($buttonData['platforms'])->toHaveCount(2);
            expect($buttonData['share_urls'])->toHaveCount(2);
        });
    });

    describe('cache management', function () {
        it('clears cache for specific model', function () {
            config(['social-share.cache.enabled' => true]);

            // Generate and cache some URLs
            $this->service->generateShareUrls($this->event, ['facebook'], 'en');
            $cacheKey = $this->service->getCacheKey($this->event, ['facebook'], 'en');

            expect(Cache::has($cacheKey))->toBeTrue();

            // Clear cache
            $this->service->clearCache($this->event);

            expect(Cache::has($cacheKey))->toBeFalse();
        });

        it('clears all social share cache', function () {
            config(['social-share.cache.enabled' => true]);

            // Generate and cache some URLs
            $this->service->generateShareUrls($this->event, ['facebook'], 'en');

            // Clear all cache
            $this->service->clearAllCache();

            $cacheKey = $this->service->getCacheKey($this->event, ['facebook'], 'en');
            expect(Cache::has($cacheKey))->toBeFalse();
        });
    });

    describe('error handling', function () {
        it('handles invalid platforms gracefully', function () {
            $shareUrls = $this->service->generateShareUrls($this->event, ['invalid', 'facebook'], 'en');

            expect($shareUrls)->toHaveCount(1);
            expect($shareUrls)->toHaveKey('facebook');
            expect($shareUrls)->not()->toHaveKey('invalid');
        });

        it('handles empty platform array', function () {
            $shareUrls = $this->service->generateShareUrls($this->event, [], 'en');

            expect($shareUrls)->toBeArray();
            // Should return all enabled platforms when empty array passed
            expect(count($shareUrls))->toBeGreaterThan(0);
        });

        it('handles tracking failures gracefully', function () {
            // Mock rate limiting to trigger an exception
            config(['social-share.rate_limiting.enabled' => true]);
            config(['social-share.rate_limiting.max_shares_per_ip_per_hour' => 0]);

            expect(fn () => $this->service->trackShare($this->event, 'facebook', null, '192.168.1.1'))
                ->toThrow();
        });
    });
});
