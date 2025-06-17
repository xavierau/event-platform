<?php

namespace App\Modules\CMS\Actions;

use App\Modules\CMS\DataTransferObjects\CmsPageData;
use App\Modules\CMS\Models\CmsPage;
use Illuminate\Support\Str;

class UpsertCmsPageAction
{
    public function execute(CmsPageData $data, ?CmsPage $page = null): CmsPage
    {
        $page = $page ?? new CmsPage();

        // Fill basic attributes
        $page->fill([
            'title' => $data->title,
            'slug' => $data->slug,
            'content' => $data->content,
            'meta_description' => $data->meta_description,
            'meta_keywords' => $data->meta_keywords,
            'is_published' => $data->is_published ?? false,
            'published_at' => $data->published_at ? now()->parse($data->published_at) : null,
            'author_id' => $data->author_id ?? auth()->id(),
            'sort_order' => $data->sort_order ?? 0,
        ]);

        // Generate slug if not provided
        if (empty($page->slug) && !empty($data->title['en'])) {
            $page->slug = $this->generateUniqueSlug($data->title['en'], $page->getKey());
        }

        $page->save();

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
