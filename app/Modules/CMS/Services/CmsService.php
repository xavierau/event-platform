<?php

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Actions\UpsertCmsPageAction;
use App\Modules\CMS\DataTransferObjects\CmsPageData;
use App\Modules\CMS\Models\CmsPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CmsService
{
    public function __construct(
        private UpsertCmsPageAction $upsertCmsPageAction
    ) {}

    public function createPage(CmsPageData $data): CmsPage
    {
        return $this->upsertCmsPageAction->execute($data);
    }

    public function updatePage(CmsPage $page, CmsPageData $data): CmsPage
    {
        return $this->upsertCmsPageAction->execute($data, $page);
    }

    public function deletePage(CmsPage $page): void
    {
        $page->delete();
    }

    public function findPageBySlug(string $slug): ?CmsPage
    {
        return CmsPage::bySlug($slug)->first();
    }

    public function findPublishedPageBySlug(string $slug): ?CmsPage
    {
        return CmsPage::published()->bySlug($slug)->first();
    }

    public function getAllPages(): Collection
    {
        return CmsPage::orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPaginatedPages(int $perPage = 15): LengthAwarePaginator
    {
        return CmsPage::with('author')->latest()->paginate($perPage);
    }

    public function getPublishedPages(): Collection
    {
        return CmsPage::published()
            ->orderBy('sort_order', 'asc')
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function getPaginatedPublishedPages(int $perPage = 15): LengthAwarePaginator
    {
        return CmsPage::published()
            ->orderBy('sort_order', 'asc')
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);
    }

    public function togglePublish(CmsPage $page): CmsPage
    {
        $page->update(['is_published' => !$page->is_published]);

        return $page;
    }

    public function updateSortOrder(array $pageOrders): void
    {
        foreach ($pageOrders as $order) {
            if (isset($order['id']) && isset($order['sort_order'])) {
                CmsPage::where('id', $order['id'])
                    ->update(['sort_order' => $order['sort_order']]);
            }
        }
    }

    public function searchPages(string $query): Collection
    {
        return CmsPage::where(function ($q) use ($query) {
            $q->where('title->en', 'like', "%{$query}%")
                ->orWhere('title->zh-TW', 'like', "%{$query}%")
                ->orWhere('title->zh-CN', 'like', "%{$query}%")
                ->orWhere('content->en', 'like', "%{$query}%")
                ->orWhere('content->zh-TW', 'like', "%{$query}%")
                ->orWhere('content->zh-CN', 'like', "%{$query}%")
                ->orWhere('slug', 'like', "%{$query}%");
        })
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
