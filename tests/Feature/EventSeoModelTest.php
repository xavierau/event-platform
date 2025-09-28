<?php

use App\Models\EventSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EventSeo Model', function () {
    it('has translatable fields', function () {
        expect(EventSeo::make()->getTranslatableAttributes())->toEqual([
            'meta_title',
            'meta_description',
            'keywords',
            'og_title',
            'og_description',
        ]);
    });

    it('can get translation for specific locale', function () {
        $eventSeo = new EventSeo([
            'meta_title' => [
                'en' => 'English Title',
                'zh-TW' => '中文標題',
            ],
        ]);

        expect($eventSeo->getMetaTitleForLocale('en'))->toBe('English Title');
        expect($eventSeo->getMetaTitleForLocale('zh-TW'))->toBe('中文標題');
        expect($eventSeo->getMetaTitleForLocale('fr'))->toBe('English Title'); // Falls back to default locale
    });

    it('can get meta description for specific locale', function () {
        $eventSeo = new EventSeo([
            'meta_description' => [
                'en' => 'English Description',
                'zh-TW' => '中文描述',
            ],
        ]);

        expect($eventSeo->getMetaDescriptionForLocale('en'))->toBe('English Description');
        expect($eventSeo->getMetaDescriptionForLocale('zh-TW'))->toBe('中文描述');
    });

    it('can get keywords for specific locale', function () {
        $eventSeo = new EventSeo([
            'keywords' => [
                'en' => 'keyword1, keyword2',
                'zh-TW' => '關鍵字1, 關鍵字2',
            ],
        ]);

        expect($eventSeo->getKeywordsForLocale('en'))->toBe('keyword1, keyword2');
        expect($eventSeo->getKeywordsForLocale('zh-TW'))->toBe('關鍵字1, 關鍵字2');
    });

    it('can get og title for specific locale', function () {
        $eventSeo = new EventSeo([
            'og_title' => [
                'en' => 'OG English Title',
                'zh-TW' => 'OG 中文標題',
            ],
        ]);

        expect($eventSeo->getOgTitleForLocale('en'))->toBe('OG English Title');
        expect($eventSeo->getOgTitleForLocale('zh-TW'))->toBe('OG 中文標題');
    });

    it('can get og description for specific locale', function () {
        $eventSeo = new EventSeo([
            'og_description' => [
                'en' => 'OG English Description',
                'zh-TW' => 'OG 中文描述',
            ],
        ]);

        expect($eventSeo->getOgDescriptionForLocale('en'))->toBe('OG English Description');
        expect($eventSeo->getOgDescriptionForLocale('zh-TW'))->toBe('OG 中文描述');
    });

    it('casts is_active to boolean', function () {
        $eventSeo = new EventSeo([
            'is_active' => '1',
        ]);

        expect($eventSeo->is_active)->toBeTrue();
        expect(gettype($eventSeo->is_active))->toBe('boolean');
    });
});
