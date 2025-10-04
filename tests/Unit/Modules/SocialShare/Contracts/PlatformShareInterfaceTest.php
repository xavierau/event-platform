<?php

use App\Modules\SocialShare\Contracts\PlatformShareInterface;
use App\Modules\SocialShare\Data\SocialPlatformData;

describe('PlatformShareInterface', function () {
    it('defines required methods for platform share implementations', function () {
        expect(interface_exists(PlatformShareInterface::class))->toBeTrue();

        $methods = get_class_methods(PlatformShareInterface::class);

        expect($methods)->toContain('getName');
        expect($methods)->toContain('generateShareUrl');
        expect($methods)->toContain('getIcon');
        expect($methods)->toContain('getColor');
        expect($methods)->toContain('supports');
    });

    it('platform implementations provide consistent interface', function () {
        // This test will verify that all platform implementations
        // properly implement the interface once we create them
        $platformData = SocialPlatformData::from([
            'name' => 'facebook',
            'url' => 'https://example.com/event',
            'title' => 'Test Event',
            'description' => 'Test Description',
            'hashtags' => ['test', 'event'],
            'via' => 'testuser',
        ]);

        expect($platformData)->toBeInstanceOf(SocialPlatformData::class);
        expect($platformData->name)->toBe('facebook');
        expect($platformData->url)->toBe('https://example.com/event');
        expect($platformData->title)->toBe('Test Event');
        expect($platformData->hashtags)->toBe(['test', 'event']);
    });
});
