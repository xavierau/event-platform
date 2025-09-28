<?php

use App\Models\Event;
use App\Modules\SocialShare\Actions\GenerateShareUrlAction;
use App\Modules\SocialShare\Data\SocialPlatformData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GenerateShareUrlAction', function () {
    beforeEach(function () {
        $this->event = Event::factory()->create([
            'name' => ['en' => 'Test Event', 'zh-TW' => '測試活動'],
            'description' => ['en' => 'Test Description', 'zh-TW' => '測試描述'],
            'slug' => ['en' => 'test-event', 'zh-TW' => 'test-event-tw'],
            'event_status' => 'published',
        ]);

        $this->action = new GenerateShareUrlAction;
    });

    describe('URL generation', function () {
        it('generates correct Facebook share URL', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'description' => 'Test Description',
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toContain('facebook.com/sharer/sharer.php');
            expect($shareUrl)->toContain('u=https%3A//example.com/event');
            expect($shareUrl)->toContain('quote=Test%20Event');
        });

        it('generates correct Twitter share URL', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'twitter',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'hashtags' => ['test', 'event'],
                'via' => 'testuser',
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toContain('twitter.com/intent/tweet');
            expect($shareUrl)->toContain('url=https%3A//example.com/event');
            expect($shareUrl)->toContain('text=Test%20Event');
            expect($shareUrl)->toContain('hashtags=test,event');
            expect($shareUrl)->toContain('via=testuser');
        });

        it('generates correct LinkedIn share URL', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'linkedin',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toContain('linkedin.com/sharing/share-offsite');
            expect($shareUrl)->toContain('url=https%3A//example.com/event');
        });

        it('generates correct WhatsApp share URL', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'whatsapp',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toContain('wa.me/');
            expect($shareUrl)->toContain('text=Test%20Event%20https%3A//example.com/event');
        });

        it('generates correct email share URL', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'email',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'description' => 'Test Description',
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toContain('mailto:');
            expect($shareUrl)->toContain('subject=Test%20Event');
            expect($shareUrl)->toContain('body=Test%20Description');
            expect($shareUrl)->toContain('https%3A//example.com/event');
        });
    });

    describe('special platform handling', function () {
        it('returns null for WeChat as it requires QR code generation', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'wechat',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toBeNull();
        });

        it('handles unsupported platforms gracefully', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'unsupported',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
            ]);

            expect(fn () => $this->action->execute($platformData))
                ->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('URL encoding and special characters', function () {
        it('properly encodes URLs with special characters', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'facebook',
                'url' => 'https://example.com/event?id=123&utm_source=test',
                'title' => 'Test Event with & special chars!',
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toContain(urlencode('https://example.com/event?id=123&utm_source=test'));
            expect($shareUrl)->toContain(urlencode('Test Event with & special chars!'));
        });

        it('handles Unicode characters correctly', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'twitter',
                'url' => 'https://example.com/event',
                'title' => '測試活動 🎉',
                'hashtags' => ['測試', '活動'],
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->toContain(urlencode('測試活動 🎉'));
            expect($shareUrl)->toContain('hashtags='.urlencode('測試,活動'));
        });
    });

    describe('parameter handling', function () {
        it('skips empty parameters', function () {
            $platformData = SocialPlatformData::from([
                'name' => 'twitter',
                'url' => 'https://example.com/event',
                'title' => 'Test Event',
                'hashtags' => [],
                'via' => null,
            ]);

            $shareUrl = $this->action->execute($platformData);

            expect($shareUrl)->not()->toContain('hashtags=');
            expect($shareUrl)->not()->toContain('via=');
            expect($shareUrl)->toContain('url=');
            expect($shareUrl)->toContain('text=');
        });

        it('handles long titles by truncating when needed', function () {
            $longTitle = str_repeat('A very long title ', 20);

            $platformData = SocialPlatformData::from([
                'name' => 'twitter',
                'url' => 'https://example.com/event',
                'title' => $longTitle,
            ]);

            $shareUrl = $this->action->execute($platformData);

            // Twitter has character limits, so we should truncate
            $decodedUrl = urldecode($shareUrl);
            expect(strlen($decodedUrl))->toBeLessThan(280 + 100); // Some buffer for URL overhead
        });
    });

    describe('from shareable model', function () {
        it('can generate URLs from shareable models', function () {
            $shareUrls = $this->action->generateForShareable($this->event, ['facebook', 'twitter'], 'en');

            expect($shareUrls)->toHaveCount(2);
            expect($shareUrls)->toHaveKey('facebook');
            expect($shareUrls)->toHaveKey('twitter');
            expect($shareUrls['facebook'])->toContain('facebook.com/sharer');
            expect($shareUrls['twitter'])->toContain('twitter.com/intent/tweet');
        });

        it('uses correct locale for shareable model data', function () {
            $shareUrls = $this->action->generateForShareable($this->event, ['facebook'], 'zh-TW');

            expect($shareUrls['facebook'])->toContain(urlencode('測試活動'));
        });

        it('handles invalid platforms gracefully', function () {
            $shareUrls = $this->action->generateForShareable($this->event, ['facebook', 'invalid', 'twitter'], 'en');

            expect($shareUrls)->toHaveCount(2);
            expect($shareUrls)->not()->toHaveKey('invalid');
        });
    });
});
