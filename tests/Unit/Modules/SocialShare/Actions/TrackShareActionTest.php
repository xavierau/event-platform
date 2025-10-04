<?php

use App\Models\Event;
use App\Models\User;
use App\Modules\SocialShare\Actions\TrackShareAction;
use App\Modules\SocialShare\Data\ShareAnalyticData;
use App\Modules\SocialShare\Models\SocialShareAnalytic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

describe('TrackShareAction', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create();
        $this->user = User::factory()->create();
        $this->action = new TrackShareAction;
    });

    describe('tracking share analytics', function () {
        it('tracks share for authenticated user', function () {
            $analyticData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Test Browser)',
                'referrer' => 'https://example.com',
                'metadata' => ['campaign' => 'summer2024'],
            ]);

            $analytic = $this->action->execute($analyticData);

            expect($analytic)->toBeInstanceOf(SocialShareAnalytic::class);
            expect($analytic->shareable_type)->toBe('App\\Models\\Event');
            expect($analytic->shareable_id)->toBe($this->event->id);
            expect($analytic->platform)->toBe('facebook');
            expect($analytic->user_id)->toBe($this->user->id);
            expect($analytic->ip_address)->toBe('192.168.1.1');
            expect($analytic->metadata)->toBe(['campaign' => 'summer2024']);

            $this->assertDatabaseHas('social_share_analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.1',
            ]);
        });

        it('tracks share for anonymous user', function () {
            $analyticData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
                'ip_address' => '192.168.1.2',
            ]);

            $analytic = $this->action->execute($analyticData);

            expect($analytic->user_id)->toBeNull();
            expect($analytic->platform)->toBe('twitter');

            $this->assertDatabaseHas('social_share_analytics', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
                'user_id' => null,
                'ip_address' => '192.168.1.2',
            ]);
        });

        it('handles different platforms', function () {
            $platforms = ['facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram', 'wechat', 'weibo', 'email'];

            foreach ($platforms as $platform) {
                $analyticData = ShareAnalyticData::from([
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => $platform,
                    'ip_address' => '192.168.1.1',
                ]);

                $analytic = $this->action->execute($analyticData);

                expect($analytic->platform)->toBe($platform);
            }

            expect(SocialShareAnalytic::count())->toBe(count($platforms));
        });
    });

    describe('tracking from HTTP request', function () {
        it('tracks share from HTTP request with user', function () {
            $request = Request::create('/api/social-share/track', 'POST', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'metadata' => ['source' => 'website'],
            ], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
                'HTTP_REFERER' => 'https://example.com',
                'REMOTE_ADDR' => '192.168.1.1',
            ]);

            $analytic = $this->action->trackFromRequest($request, $this->user);

            expect($analytic->user_id)->toBe($this->user->id);
            expect($analytic->ip_address)->toBe('192.168.1.1');
            expect($analytic->user_agent)->toBe('Mozilla/5.0 (Test Browser)');
            expect($analytic->referrer)->toBe('https://example.com');
            expect($analytic->metadata)->toBe(['source' => 'website']);
        });

        it('tracks share from HTTP request without user', function () {
            $request = Request::create('/api/social-share/track', 'POST', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
            ], [], [], [
                'REMOTE_ADDR' => '192.168.1.2',
            ]);

            $analytic = $this->action->trackFromRequest($request);

            expect($analytic->user_id)->toBeNull();
            expect($analytic->ip_address)->toBe('192.168.1.2');
            expect($analytic->platform)->toBe('twitter');
        });

        it('handles missing IP address gracefully', function () {
            $request = Request::create('/api/social-share/track', 'POST', [
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
            ]);

            $analytic = $this->action->trackFromRequest($request);

            expect($analytic->ip_address)->toBe('127.0.0.1'); // Default fallback
        });
    });

    describe('validation and error handling', function () {
        it('validates required fields', function () {
            expect(fn () => ShareAnalyticData::from([
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]))->toThrow(); // Missing shareable_type

            expect(fn () => ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]))->toThrow(); // Missing shareable_id
        });

        it('validates platform values', function () {
            expect(fn () => ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'invalid_platform',
                'ip_address' => '192.168.1.1',
            ]))->toThrow();
        });

        it('validates IP address format', function () {
            expect(fn () => ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => 'invalid-ip',
            ]))->toThrow();
        });
    });

    describe('rate limiting', function () {
        it('respects rate limiting configuration', function () {
            // Mock configuration for testing
            config(['social-share.rate_limiting.enabled' => true]);
            config(['social-share.rate_limiting.max_shares_per_ip_per_hour' => 2]);

            $analyticData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            // First two should succeed
            $this->action->execute($analyticData);
            $this->action->execute($analyticData);

            // Third should be rate limited
            expect(fn () => $this->action->execute($analyticData))
                ->toThrow(\Exception::class);
        });

        it('allows tracking when rate limiting is disabled', function () {
            config(['social-share.rate_limiting.enabled' => false]);

            $analyticData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            // Should allow multiple shares
            for ($i = 0; $i < 5; $i++) {
                $analytic = $this->action->execute($analyticData);
                expect($analytic)->toBeInstanceOf(SocialShareAnalytic::class);
            }

            expect(SocialShareAnalytic::count())->toBe(5);
        });
    });

    describe('bulk tracking', function () {
        it('can track multiple shares at once', function () {
            $shareData = [
                [
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => 'facebook',
                    'ip_address' => '192.168.1.1',
                ],
                [
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => 'twitter',
                    'ip_address' => '192.168.1.2',
                ],
            ];

            $analytics = $this->action->trackMultiple($shareData);

            expect($analytics)->toHaveCount(2);
            expect($analytics[0]->platform)->toBe('facebook');
            expect($analytics[1]->platform)->toBe('twitter');
            expect(SocialShareAnalytic::count())->toBe(2);
        });

        it('handles partial failures in bulk tracking', function () {
            $shareData = [
                [
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => 'facebook',
                    'ip_address' => '192.168.1.1',
                ],
                [
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => 'invalid_platform',
                    'ip_address' => '192.168.1.2',
                ],
            ];

            $result = $this->action->trackMultipleSafe($shareData);

            expect($result['successful'])->toHaveCount(1);
            expect($result['failed'])->toHaveCount(1);
            expect(SocialShareAnalytic::count())->toBe(1);
        });
    });
});
