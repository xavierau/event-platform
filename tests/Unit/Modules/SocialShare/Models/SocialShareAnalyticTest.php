<?php

use App\Models\Event;
use App\Models\User;
use App\Modules\SocialShare\Models\SocialShareAnalytic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('SocialShareAnalytic Model', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create();
        $this->user = User::factory()->create();
    });

    describe('database structure', function () {
        it('has correct table structure', function () {
            expect(Schema::hasTable('social_share_analytics'))->toBeTrue();

            $columns = [
                'id', 'shareable_type', 'shareable_id', 'platform', 'user_id',
                'ip_address', 'user_agent', 'referrer', 'metadata', 'created_at', 'updated_at',
            ];

            foreach ($columns as $column) {
                expect(Schema::hasColumn('social_share_analytics', $column))
                    ->toBeTrue("Column {$column} should exist");
            }
        });

        it('has correct indexes', function () {
            $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('social_share_analytics');

            $indexNames = array_keys($indexes);
            expect($indexNames)->toContain('social_share_analytics_shareable_type_shareable_id_index');
            expect($indexNames)->toContain('social_share_analytics_platform_index');
            expect($indexNames)->toContain('social_share_analytics_created_at_index');
        });
    });

    describe('model creation and validation', function () {
        it('can create a social share analytic record', function () {
            $analytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Test Browser)',
                'referrer' => 'https://example.com',
                'metadata' => ['campaign' => 'summer2024'],
            ]);

            expect($analytic)->toBeInstanceOf(SocialShareAnalytic::class);
            expect($analytic->shareable_type)->toBe('App\\Models\\Event');
            expect($analytic->shareable_id)->toBe($this->event->id);
            expect($analytic->platform)->toBe('facebook');
            expect($analytic->user_id)->toBe($this->user->id);
            expect($analytic->ip_address)->toBe('192.168.1.1');
            expect($analytic->metadata)->toBe(['campaign' => 'summer2024']);
        });

        it('can create record for anonymous user', function () {
            $analytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
                'ip_address' => '192.168.1.1',
            ]);

            expect($analytic->user_id)->toBeNull();
            expect($analytic->platform)->toBe('twitter');
        });

        it('validates platform enum values', function () {
            $validPlatforms = ['facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram', 'wechat', 'weibo', 'email'];

            foreach ($validPlatforms as $platform) {
                $analytic = SocialShareAnalytic::create([
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => $platform,
                    'ip_address' => '192.168.1.1',
                ]);

                expect($analytic->platform)->toBe($platform);
            }
        });
    });

    describe('relationships', function () {
        it('belongs to user when user_id is set', function () {
            $analytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.1',
            ]);

            expect($analytic->user)->toBeInstanceOf(User::class);
            expect($analytic->user->id)->toBe($this->user->id);
        });

        it('has null user relationship for anonymous shares', function () {
            $analytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            expect($analytic->user)->toBeNull();
        });

        it('has polymorphic relationship to shareable models', function () {
            $analytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            expect($analytic->shareable)->toBeInstanceOf(Event::class);
            expect($analytic->shareable->id)->toBe($this->event->id);
        });
    });

    describe('scopes and query methods', function () {
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

        it('can filter by platform', function () {
            $facebookShares = SocialShareAnalytic::forPlatform('facebook')->get();
            $twitterShares = SocialShareAnalytic::forPlatform('twitter')->get();

            expect($facebookShares)->toHaveCount(2);
            expect($twitterShares)->toHaveCount(1);
        });

        it('can filter by authenticated users', function () {
            $authenticatedShares = SocialShareAnalytic::authenticated()->get();
            $anonymousShares = SocialShareAnalytic::anonymous()->get();

            expect($authenticatedShares)->toHaveCount(2);
            expect($anonymousShares)->toHaveCount(1);
        });

        it('can filter by date range', function () {
            $recentShares = SocialShareAnalytic::dateRange(now()->subDays(1), now())->get();
            $olderShares = SocialShareAnalytic::dateRange(now()->subDays(5), now()->subDays(2))->get();

            expect($recentShares)->toHaveCount(1);
            expect($olderShares)->toHaveCount(2);
        });

        it('can filter by shareable model', function () {
            $eventShares = SocialShareAnalytic::forShareable($this->event)->get();

            expect($eventShares)->toHaveCount(3);
            expect($eventShares->first()->shareable_id)->toBe($this->event->id);
        });
    });

    describe('aggregation methods', function () {
        beforeEach(function () {
            // Create test data for different platforms
            collect(['facebook', 'twitter', 'linkedin'])->each(function ($platform) {
                SocialShareAnalytic::create([
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => $platform,
                    'ip_address' => '192.168.1.1',
                ]);
            });

            // Additional Facebook share
            SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.2',
            ]);
        });

        it('can get total share count', function () {
            $totalShares = SocialShareAnalytic::getTotalShares();

            expect($totalShares)->toBe(4);
        });

        it('can get shares by platform', function () {
            $sharesByPlatform = SocialShareAnalytic::getSharesByPlatform();

            expect($sharesByPlatform)->toHaveKey('facebook');
            expect($sharesByPlatform)->toHaveKey('twitter');
            expect($sharesByPlatform)->toHaveKey('linkedin');
            expect($sharesByPlatform['facebook'])->toBe(2);
            expect($sharesByPlatform['twitter'])->toBe(1);
            expect($sharesByPlatform['linkedin'])->toBe(1);
        });

        it('can get top platforms', function () {
            $topPlatforms = SocialShareAnalytic::getTopPlatforms(2);

            expect($topPlatforms)->toHaveCount(2);
            expect($topPlatforms->first()['platform'])->toBe('facebook');
            expect($topPlatforms->first()['share_count'])->toBe(2);
        });
    });

    describe('helper methods', function () {
        it('can check if share is mobile', function () {
            $mobileAnalytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
            ]);

            $desktopAnalytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);

            expect($mobileAnalytic->isMobile())->toBeTrue();
            expect($desktopAnalytic->isMobile())->toBeFalse();
        });

        it('can get browser information', function () {
            $analytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ]);

            expect($analytic->getBrowser())->toBe('Chrome');
        });

        it('handles null user agent gracefully', function () {
            $analytic = SocialShareAnalytic::create([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            expect($analytic->isMobile())->toBeFalse();
            expect($analytic->getBrowser())->toBe('Unknown');
        });
    });
});
