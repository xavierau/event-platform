<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Services\CmsService;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function __construct(
        private CmsService $cmsService
    ) {}

    public function show(string $slug): Response
    {
        $page = $this->cmsService->findPublishedPageBySlug($slug);

        if (!$page) {
            abort(404);
        }

        return Inertia::render('Public/CmsPage', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'meta_description' => $page->meta_description,
                'meta_keywords' => $page->meta_keywords,
                'published_at' => $page->published_at?->format('Y-m-d H:i:s'),
                'featured_image_url' => $page->getFeaturedImageUrl(),
                'featured_image_thumb_url' => $page->getFeaturedImageThumbUrl(),
                'author' => $page->author ? [
                    'name' => $page->author->name,
                ] : null,
            ],
        ]);
    }

    public function index(): Response
    {
        $pages = $this->cmsService->getPublishedPages();

        return Inertia::render('Public/CmsPages/Index', [
            'pages' => $pages->map(function ($page) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'meta_description' => $page->meta_description,
                    'published_at' => $page->published_at?->format('Y-m-d H:i:s'),
                    'featured_image_thumb_url' => $page->getFeaturedImageThumbUrl(),
                    'author' => $page->author ? [
                        'name' => $page->author->name,
                    ] : null,
                ];
            }),
        ]);
    }
}
