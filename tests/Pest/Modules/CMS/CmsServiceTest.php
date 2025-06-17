<?php

use App\Modules\CMS\DataTransferObjects\CmsPageData;
use App\Modules\CMS\Models\CmsPage;
use App\Modules\CMS\Services\CmsService;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(CmsService::class);
});

describe('CmsService', function () {
    test('can create a CMS page', function () {
        $data = CmsPageData::from([
            'title' => [
                'en' => 'Test Page',
                'zh-TW' => '測試頁面',
            ],
            'slug' => 'test-page',
            'content' => [
                'en' => 'This is test content',
                'zh-TW' => '這是測試內容',
            ],
            'meta_description' => [
                'en' => 'Test meta description',
            ],
            'meta_keywords' => [
                'en' => 'test, cms, page',
            ],
            'is_published' => true,
            'published_at' => null,
            'author_id' => $this->user->id,
            'sort_order' => 1,
            'featured_image' => null,
            'gallery_images' => null,
        ]);

        $page = $this->service->createPage($data);

        expect($page)
            ->toBeInstanceOf(CmsPage::class)
            ->and($page->getTranslations('title'))
            ->toEqual(['en' => 'Test Page', 'zh-TW' => '測試頁面'])
            ->and($page->slug)
            ->toBe('test-page')
            ->and($page->getTranslations('content'))
            ->toEqual(['en' => 'This is test content', 'zh-TW' => '這是測試內容'])
            ->and($page->is_published)
            ->toBeTrue()
            ->and($page->author_id)
            ->toBe($this->user->id);
    });

    test('can update an existing CMS page', function () {
        $page = CmsPage::factory()->create([
            'title' => ['en' => 'Original Title'],
            'content' => ['en' => 'Original content'],
        ]);

        $data = CmsPageData::from([
            'title' => [
                'en' => 'Updated Title',
                'zh-TW' => '更新標題',
            ],
            'slug' => $page->slug,
            'content' => [
                'en' => 'Updated content',
                'zh-TW' => '更新內容',
            ],
            'meta_description' => null,
            'meta_keywords' => null,
            'is_published' => $page->is_published,
            'published_at' => null,
            'author_id' => $page->author_id,
            'sort_order' => $page->sort_order,
            'featured_image' => null,
            'gallery_images' => null,
        ]);

        $updatedPage = $this->service->updatePage($page, $data);

        expect($updatedPage->getTranslations('title'))
            ->toEqual(['en' => 'Updated Title', 'zh-TW' => '更新標題'])
            ->and($updatedPage->getTranslations('content'))
            ->toEqual(['en' => 'Updated content', 'zh-TW' => '更新內容']);
    });

    test('can delete a CMS page', function () {
        $page = CmsPage::factory()->create();

        $result = $this->service->deletePage($page);

        expect($result)->toBeTrue();
        expect(CmsPage::find($page->id))->toBeNull();
    });

    test('can find page by slug', function () {
        $page = CmsPage::factory()->create(['slug' => 'unique-test-slug']);

        $foundPage = $this->service->findPageBySlug('unique-test-slug');

        expect($foundPage)
            ->toBeInstanceOf(CmsPage::class)
            ->and($foundPage->id)
            ->toBe($page->id);
    });

    test('can find published page by slug', function () {
        $publishedPage = CmsPage::factory()->published()->create(['slug' => 'published-page']);
        $unpublishedPage = CmsPage::factory()->unpublished()->create(['slug' => 'unpublished-page']);

        $foundPublished = $this->service->findPublishedPageBySlug('published-page');
        $foundUnpublished = $this->service->findPublishedPageBySlug('unpublished-page');

        expect($foundPublished)
            ->toBeInstanceOf(CmsPage::class)
            ->and($foundPublished->id)
            ->toBe($publishedPage->id)
            ->and($foundUnpublished)
            ->toBeNull();
    });

    test('can get all pages ordered by sort order', function () {
        CmsPage::factory()->create(['sort_order' => 3]);
        CmsPage::factory()->create(['sort_order' => 1]);
        CmsPage::factory()->create(['sort_order' => 2]);

        $pages = $this->service->getAllPages();

        expect($pages)
            ->toHaveCount(3)
            ->and($pages->first()->sort_order)
            ->toBe(1)
            ->and($pages->last()->sort_order)
            ->toBe(3);
    });

    test('can get only published pages', function () {
        CmsPage::factory()->published()->count(2)->create();
        CmsPage::factory()->unpublished()->count(3)->create();

        $publishedPages = $this->service->getPublishedPages();

        expect($publishedPages)
            ->toHaveCount(2)
            ->and($publishedPages->every(fn($page) => $page->is_published))
            ->toBeTrue();
    });

    test('can toggle publish status', function () {
        $page = CmsPage::factory()->unpublished()->create();

        $toggledPage = $this->service->togglePublishStatus($page);

        expect($toggledPage->is_published)
            ->toBeTrue()
            ->and($toggledPage->published_at)
            ->not->toBeNull();

        $toggledAgain = $this->service->togglePublishStatus($toggledPage);

        expect($toggledAgain->is_published)->toBeFalse();
    });

    test('can search pages by content', function () {
        CmsPage::factory()->create([
            'title' => ['en' => 'Searchable Title'],
            'content' => ['en' => 'This contains searchable content'],
        ]);
        CmsPage::factory()->create([
            'title' => ['en' => 'Other Title'],
            'content' => ['en' => 'Different content here'],
        ]);

        $results = $this->service->searchPages('searchable');

        expect($results)
            ->toHaveCount(1)
            ->and($results->first()->getTranslation('title', 'en'))
            ->toBe('Searchable Title');
    });
});
