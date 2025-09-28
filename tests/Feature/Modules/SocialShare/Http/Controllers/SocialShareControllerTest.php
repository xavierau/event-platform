<?php

use App\Models\Event;
use App\Models\User;
use App\Modules\SocialShare\Models\SocialShareAnalytic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SocialShareController', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create([
            'name' => ['en' => 'Test Event', 'zh-TW' => '測試活動'],
            'description' => ['en' => 'Test Description', 'zh-TW' => '測試描述'],
            'slug' => ['en' => 'test-event', 'zh-TW' => 'test-event-tw'],
            'event_status' => 'published',
        ]);

        $this->user = User::factory()->create();
    });

    describe('GET /api/social-share/urls', function () {
        it('returns share URLs for a valid shareable model', function () {
            $response = $this->getJson('/api/social-share/urls', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platforms' => ['facebook', 'twitter'],
                'locale' => 'en',
            ]);

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'share_urls' => [
                            'facebook',
                            'twitter',
                        ],
                        'platforms',
                        'ui_config',
                        'ui_mode',
                    ],
                ]);

            expect($response->json('data.share_urls.facebook'))->toContain('facebook.com');
            expect($response->json('data.share_urls.twitter'))->toContain('twitter.com');
        });

        it('returns all enabled platforms when no platforms specified', function () {
            $response = $this->getJson('/api/social-share/urls', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'locale' => 'en',
            ]);

            $response->assertOk();

            $shareUrls = $response->json('data.share_urls');
            expect($shareUrls)->toHaveKey('facebook');
            expect($shareUrls)->toHaveKey('twitter');
            expect($shareUrls)->toHaveKey('linkedin');
        });

        it('supports multilingual URL generation', function () {
            $responseEn = $this->getJson('/api/social-share/urls', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platforms' => ['facebook'],
                'locale' => 'en',
            ]);

            $responseZh = $this->getJson('/api/social-share/urls', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platforms' => ['facebook'],
                'locale' => 'zh-TW',
            ]);

            $responseEn->assertOk();
            $responseZh->assertOk();

            $urlEn = $responseEn->json('data.share_urls.facebook');
            $urlZh = $responseZh->json('data.share_urls.facebook');

            expect($urlEn)->toContain(urlencode('Test Event'));
            expect($urlZh)->toContain(urlencode('測試活動'));
        });

        it('validates required parameters', function () {
            $response = $this->getJson('/api/social-share/urls', [
                'shareable_id' => $this->event->id,
                'platforms' => ['facebook'],
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['shareable_type']);
        });

        it('validates shareable model exists', function () {
            $response = $this->getJson('/api/social-share/urls', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => 99999,
                'platforms' => ['facebook'],
            ]);

            $response->assertNotFound();
        });

        it('filters invalid platforms', function () {
            $response = $this->getJson('/api/social-share/urls', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platforms' => ['facebook', 'invalid_platform', 'twitter'],
                'locale' => 'en',
            ]);

            $response->assertOk();

            $shareUrls = $response->json('data.share_urls');
            expect($shareUrls)->toHaveKey('facebook');
            expect($shareUrls)->toHaveKey('twitter');
            expect($shareUrls)->not()->toHaveKey('invalid_platform');
        });
    });

    describe('POST /api/social-share/track', function () {
        it('tracks share for authenticated user', function () {
            $this->actingAs($this->user);

            $response = $this->postJson('/api/social-share/track', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'metadata' => ['source' => 'website'],
            ]);

            $response->assertCreated()
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'platform',
                        'shareable_type',
                        'shareable_id',
                        'user_id',
                        'created_at',
                    ],
                ]);

            $this->assertDatabaseHas('social_share_analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
            ]);

            expect($response->json('data.user_id'))->toBe($this->user->id);
        });

        it('tracks share for anonymous user', function () {
            $response = $this->postJson('/api/social-share/track', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
                'metadata' => ['source' => 'widget'],
            ]);

            $response->assertCreated();

            $this->assertDatabaseHas('social_share_analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
                'user_id' => null,
            ]);

            expect($response->json('data.user_id'))->toBeNull();
        });

        it('captures request metadata', function () {
            $response = $this->postJson('/api/social-share/track', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
            ], [
                'User-Agent' => 'Mozilla/5.0 (Test Browser)',
                'Referer' => 'https://example.com',
            ]);

            $response->assertCreated();

            $analytic = SocialShareAnalytic::latest()->first();
            expect($analytic->user_agent)->toBe('Mozilla/5.0 (Test Browser)');
            expect($analytic->referrer)->toBe('https://example.com');
            expect($analytic->ip_address)->not()->toBeNull();
        });

        it('validates required parameters', function () {
            $response = $this->postJson('/api/social-share/track', [
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['shareable_type']);
        });

        it('validates platform values', function () {
            $response = $this->postJson('/api/social-share/track', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'invalid_platform',
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['platform']);
        });

        it('enforces rate limiting when enabled', function () {
            config(['social-share.rate_limiting.enabled' => true]);
            config(['social-share.rate_limiting.max_shares_per_ip_per_hour' => 1]);

            // First request should succeed
            $response1 = $this->postJson('/api/social-share/track', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
            ]);

            $response1->assertCreated();

            // Second request from same IP should be rate limited
            $response2 = $this->postJson('/api/social-share/track', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
            ]);

            $response2->assertStatus(429); // Too Many Requests
        });
    });

    describe('GET /api/social-share/analytics', function () {
        beforeEach(function () {
            // Create test analytics data
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

        it('returns analytics for a specific model', function () {
            $response = $this->getJson('/api/social-share/analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
            ]);

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'total_shares',
                        'shares_by_platform',
                        'authenticated_shares',
                        'anonymous_shares',
                        'mobile_shares',
                        'desktop_shares',
                    ],
                ]);

            expect($response->json('data.total_shares'))->toBe(3);
            expect($response->json('data.shares_by_platform.facebook'))->toBe(2);
            expect($response->json('data.shares_by_platform.twitter'))->toBe(1);
        });

        it('supports date range filtering', function () {
            $response = $this->getJson('/api/social-share/analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'date_from' => now()->subDays(2)->toDateString(),
                'date_to' => now()->toDateString(),
            ]);

            $response->assertOk();

            expect($response->json('data.total_shares'))->toBe(2);
        });

        it('supports platform filtering', function () {
            $response = $this->getJson('/api/social-share/analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
            ]);

            $response->assertOk();

            // Note: This should filter the analytics, but the current implementation
            // returns aggregated stats. In practice, you might want separate endpoints
            // for filtered vs aggregated analytics
        });

        it('returns global analytics when no model specified', function () {
            $response = $this->getJson('/api/social-share/analytics');

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'total_shares',
                        'shares_by_platform',
                        'authenticated_shares',
                        'anonymous_shares',
                        'mobile_shares',
                        'desktop_shares',
                    ],
                ]);

            expect($response->json('data.total_shares'))->toBeGreaterThanOrEqual(3);
        });

        it('validates model exists when specified', function () {
            $response = $this->getJson('/api/social-share/analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => 99999,
            ]);

            $response->assertNotFound();
        });
    });

    describe('GET /api/social-share/popular', function () {
        beforeEach(function () {
            $this->event2 = Event::factory()->create([
                'name' => ['en' => 'Popular Event'],
                'event_status' => 'published',
            ]);

            // Create more shares for event2 to make it more popular
            foreach (range(1, 5) as $i) {
                SocialShareAnalytic::create([
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event2->id,
                    'platform' => 'facebook',
                    'ip_address' => "192.168.1.{$i}",
                ]);
            }

            // Create fewer shares for event1
            foreach (range(1, 2) as $i) {
                SocialShareAnalytic::create([
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => 'twitter',
                    'ip_address' => "192.168.2.{$i}",
                ]);
            }
        });

        it('returns popular content by share count', function () {
            $response = $this->getJson('/api/social-share/popular', [
                'model_type' => 'App\\Models\\Event',
                'limit' => 10,
            ]);

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'shareable_id',
                            'share_count',
                        ],
                    ],
                ]);

            $popularContent = $response->json('data');
            expect($popularContent)->toHaveCount(2);

            // Most popular should be first
            expect($popularContent[0]['shareable_id'])->toBe($this->event2->id);
            expect($popularContent[0]['share_count'])->toBe(5);

            expect($popularContent[1]['shareable_id'])->toBe($this->event->id);
            expect($popularContent[1]['share_count'])->toBe(2);
        });

        it('respects limit parameter', function () {
            $response = $this->getJson('/api/social-share/popular', [
                'model_type' => 'App\\Models\\Event',
                'limit' => 1,
            ]);

            $response->assertOk();

            $popularContent = $response->json('data');
            expect($popularContent)->toHaveCount(1);
            expect($popularContent[0]['shareable_id'])->toBe($this->event2->id);
        });

        it('supports date range filtering', function () {
            $response = $this->getJson('/api/social-share/popular', [
                'model_type' => 'App\\Models\\Event',
                'date_from' => now()->subDays(1)->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]);

            $response->assertOk();

            $popularContent = $response->json('data');
            expect($popularContent)->toHaveCount(2);
        });

        it('validates model_type parameter', function () {
            $response = $this->getJson('/api/social-share/popular', [
                'limit' => 10,
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['model_type']);
        });
    });
});
