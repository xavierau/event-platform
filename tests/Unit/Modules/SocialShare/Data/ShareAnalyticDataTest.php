<?php

use App\Models\Event;
use App\Models\User;
use App\Modules\SocialShare\Data\ShareAnalyticData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ShareAnalyticData', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create();
        $this->user = User::factory()->create();
    });

    describe('creation and validation', function () {
        it('can be created with valid data', function () {
            $data = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Test Browser)',
                'referrer' => 'https://example.com',
                'metadata' => ['campaign' => 'summer2024'],
            ]);

            expect($data)->toBeInstanceOf(ShareAnalyticData::class);
            expect($data->shareable_type)->toBe('App\\Models\\Event');
            expect($data->shareable_id)->toBe($this->event->id);
            expect($data->platform)->toBe('facebook');
            expect($data->user_id)->toBe($this->user->id);
            expect($data->ip_address)->toBe('192.168.1.1');
            expect($data->user_agent)->toBe('Mozilla/5.0 (Test Browser)');
            expect($data->referrer)->toBe('https://example.com');
            expect($data->metadata)->toBe(['campaign' => 'summer2024']);
        });

        it('can be created with minimal data for anonymous users', function () {
            $data = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'twitter',
                'ip_address' => '192.168.1.1',
            ]);

            expect($data->shareable_type)->toBe('App\\Models\\Event');
            expect($data->shareable_id)->toBe($this->event->id);
            expect($data->platform)->toBe('twitter');
            expect($data->user_id)->toBeNull();
            expect($data->ip_address)->toBe('192.168.1.1');
            expect($data->user_agent)->toBeNull();
            expect($data->referrer)->toBeNull();
            expect($data->metadata)->toBe([]);
        });

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

            expect(fn () => ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'ip_address' => '192.168.1.1',
            ]))->toThrow(); // Missing platform

            expect(fn () => ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
            ]))->toThrow(); // Missing ip_address
        });

        it('validates ip address format', function () {
            expect(fn () => ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => 'invalid-ip',
            ]))->toThrow();
        });

        it('validates platform values', function () {
            $data = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            expect($data->platform)->toBe('facebook');

            // Test other valid platforms
            $validPlatforms = ['twitter', 'linkedin', 'whatsapp', 'telegram', 'wechat', 'weibo', 'email'];
            foreach ($validPlatforms as $platform) {
                $data = ShareAnalyticData::from([
                    'shareable_type' => 'App\\Models\\Event',
                    'shareable_id' => $this->event->id,
                    'platform' => $platform,
                    'ip_address' => '192.168.1.1',
                ]);

                expect($data->platform)->toBe($platform);
            }
        });
    });

    describe('helper methods', function () {
        it('can check if share was by authenticated user', function () {
            $authenticatedData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'user_id' => $this->user->id,
                'ip_address' => '192.168.1.1',
            ]);

            $anonymousData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            expect($authenticatedData->isAuthenticatedShare())->toBeTrue();
            expect($anonymousData->isAuthenticatedShare())->toBeFalse();
        });

        it('can get shareable model type name', function () {
            $data = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            expect($data->getShareableModelName())->toBe('Event');
        });

        it('can determine if mobile user agent', function () {
            $mobileData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
            ]);

            $desktopData = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);

            expect($mobileData->isMobileShare())->toBeTrue();
            expect($desktopData->isMobileShare())->toBeFalse();
        });

        it('handles null user agent gracefully', function () {
            $data = ShareAnalyticData::from([
                'shareable_type' => 'App\\Models\\Event',
                'shareable_id' => $this->event->id,
                'platform' => 'facebook',
                'ip_address' => '192.168.1.1',
            ]);

            expect($data->isMobileShare())->toBeFalse();
        });
    });

    describe('validation rules', function () {
        it('defines proper validation rules', function () {
            $rules = ShareAnalyticData::rules();

            expect($rules)->toHaveKey('shareable_type');
            expect($rules)->toHaveKey('shareable_id');
            expect($rules)->toHaveKey('platform');
            expect($rules)->toHaveKey('user_id');
            expect($rules)->toHaveKey('ip_address');
            expect($rules)->toHaveKey('user_agent');
            expect($rules)->toHaveKey('referrer');
            expect($rules)->toHaveKey('metadata');
        });

        it('enforces proper constraints', function () {
            $rules = ShareAnalyticData::rules();

            expect($rules['shareable_type'])->toContain('required');
            expect($rules['shareable_type'])->toContain('string');
            expect($rules['shareable_id'])->toContain('required');
            expect($rules['shareable_id'])->toContain('integer');
            expect($rules['platform'])->toContain('required');
            expect($rules['platform'])->toContain('string');
            expect($rules['ip_address'])->toContain('required');
            expect($rules['ip_address'])->toContain('ip');
            expect($rules['user_id'])->toContain('nullable');
            expect($rules['user_id'])->toContain('integer');
            expect($rules['metadata'])->toContain('array');
        });
    });
});
