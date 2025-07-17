<?php

namespace App\Modules\CMS\Actions;

use App\Modules\CMS\DataTransferObjects\CmsPageData;
use App\Modules\CMS\Models\CmsPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UpsertCmsPageAction
{
    public function execute(CmsPageData $data, CmsPage $page = null): CmsPage
    {
        $payload = $data->toArray();

        // If no slug is provided OR if the title was changed and the slug wasn't, regenerate the slug.
        $newSlug = Str::slug($data->title['en']);
        if (empty($payload['slug']) || ($page && $page->getTranslation('title', 'en') !== $data->title['en'] && $page->slug === $payload['slug'])) {
            $payload['slug'] = $newSlug;
        }

        // Handle the published status
        if ($data->is_published && (is_null($page) || !$page->published_at)) {
            $payload['published_at'] = now();
        } elseif (!$data->is_published) {
            $payload['published_at'] = null;
        }

        // Set default sort_order if not provided
        if (!isset($payload['sort_order'])) {
            $payload['sort_order'] = 0;
        }
        if ($page) {
            // Update
            $payload['author_id'] = $payload['author_id'] ?? request()->user()->id;
            $page->update($payload);
        } else {
            // Create
            $payload['author_id'] = request()->user()->id;
            $page = CmsPage::create($payload);
        }

        // Handle featured image
        if ($data->featured_image) {
            $page->clearMediaCollection('featured_image');
            $page->addMediaFromRequest('featured_image')
                ->toMediaCollection('featured_image');
        }

        // Handle gallery images
        if ($data->gallery_images) {
            $page->clearMediaCollection('gallery');
            foreach ($data->gallery_images as $index => $image) {
                $page->addMedia($image)
                    ->toMediaCollection('gallery');
            }
        }

        return $page->fresh();
    }

    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = CmsPage::where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
