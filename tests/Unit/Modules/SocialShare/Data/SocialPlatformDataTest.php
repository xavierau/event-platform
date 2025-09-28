<?php

use App\Modules\SocialShare\Data\SocialPlatformData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SocialPlatformData', function () {
    describe('creation and validation', function () {
        it('can be created with valid data', function () {
            $data = SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'description' => 'Test Description',
                'hashtags' => ['test', 'event'],
                'via' => 'testuser',
                'image_url' => 'https://example.com/image.jpg',
            ]);

            expect($data)->toBeInstanceOf(SocialPlatformData::class);
            expect($data->name)->toBe('facebook');
            expect($data->url)->toBe('https://example.com/event');
            expect($data->title)->toBe('Test Event');
            expect($data->description)->toBe('Test Description');
            expect($data->hashtags)->toBe(['test', 'event']);
            expect($data->via)->toBe('testuser');
            expect($data->image_url)->toBe('https://example.com/image.jpg');
        });

        it('can be created with minimal data', function () {
            $data = SocialPlatformData::from([
                'name' => 'twitter',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
            ]);

            expect($data->name)->toBe('twitter');
            expect($data->url)->toBe('https://example.com/event');
            expect($data->title)->toBe('Test Event');
            expect($data->description)->toBeNull();
            expect($data->hashtags)->toBe([]);
            expect($data->via)->toBeNull();
            expect($data->image_url)->toBeNull();
        });

        it('validates required fields', function () {
            expect(fn () => SocialPlatformData::from([
                'url' => 'https://example.com',
                'title' => 'Test',
            ]))->toThrow();

            expect(fn () => SocialPlatformData::from([
                'name' => 'facebook',
                'title' => 'Test',
            ]))->toThrow();

            expect(fn () => SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'https://example.com',
            ]))->toThrow();
        });

        it('validates URL format', function () {
            expect(fn () => SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'invalid-url',
                'title' => 'Test',
            ]))->toThrow();
        });
    });

    describe('helper methods', function () {
        it('can format hashtags for different platforms', function () {
            $data = SocialPlatformData::from([
                'name' => 'twitter',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'hashtags' => ['test', 'event', 'awesome'],
            ]);

            $formatted = $data->getFormattedHashtags();
            expect($formatted)->toBe('#test #event #awesome');
        });

        it('handles empty hashtags gracefully', function () {
            $data = SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'hashtags' => [],
            ]);

            $formatted = $data->getFormattedHashtags();
            expect($formatted)->toBe('');
        });

        it('can get truncated title for platforms with limits', function () {
            $longTitle = str_repeat('A very long title ', 20);

            $data = SocialPlatformData::from([
                'name' => 'twitter',
                'url' => 'https://example.com/event',
                'title' => $longTitle,
            ]);

            $truncated = $data->getTruncatedTitle(50);
            expect(strlen($truncated))->toBeLessThanOrEqual(50);
            expect($truncated)->toEndWith('...');
        });

        it('can get truncated description for platforms with limits', function () {
            $longDescription = str_repeat('A very long description ', 30);

            $data = SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'description' => $longDescription,
            ]);

            $truncated = $data->getTruncatedDescription(100);
            expect(strlen($truncated))->toBeLessThanOrEqual(100);
            expect($truncated)->toEndWith('...');
        });
    });

    describe('platform-specific data', function () {
        it('handles platform-specific metadata', function () {
            $data = SocialPlatformData::from([
                'name' => 'wechat',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'metadata' => [
                    'qr_code_size' => 'large',
                    'theme' => 'dark',
                ],
            ]);

            expect($data->metadata)->toBe([
                'qr_code_size' => 'large',
                'theme' => 'dark',
            ]);
        });

        it('handles null metadata gracefully', function () {
            $data = SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
            ]);

            expect($data->metadata)->toBe([]);
        });
    });

    describe('validation rules', function () {
        it('defines proper validation rules', function () {
            $rules = SocialPlatformData::rules();

            expect($rules)->toHaveKey('name');
            expect($rules)->toHaveKey('url');
            expect($rules)->toHaveKey('title');
            expect($rules)->toHaveKey('description');
            expect($rules)->toHaveKey('hashtags');
            expect($rules)->toHaveKey('hashtags.*');
            expect($rules)->toHaveKey('via');
            expect($rules)->toHaveKey('image_url');
            expect($rules)->toHaveKey('metadata');
        });

        it('enforces proper constraints', function () {
            $rules = SocialPlatformData::rules();

            expect($rules['name'])->toContain('required');
            expect($rules['name'])->toContain('string');
            expect($rules['url'])->toContain('required');
            expect($rules['url'])->toContain('url');
            expect($rules['title'])->toContain('required');
            expect($rules['title'])->toContain('string');
            expect($rules['hashtags'])->toContain('array');
            expect($rules['hashtags.*'])->toContain('string');
            expect($rules['image_url'])->toContain('url');
        });
    });
});
