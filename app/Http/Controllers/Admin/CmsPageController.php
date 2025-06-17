<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CMS\DataTransferObjects\CmsPageData;
use App\Modules\CMS\Models\CmsPage;
use App\Modules\CMS\Services\CmsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function __construct(
        private CmsService $cmsService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search');

        if ($search) {
            $pages = $this->cmsService->searchPages($search);
        } else {
            $pages = $this->cmsService->getPaginatedPages(15);
        }

        return Inertia::render('Admin/CmsPages/Index', [
            'pages' => $pages,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/CmsPages/Create');
    }

    public function store(Request $request)
    {
        try {
            $data = CmsPageData::from($request->all());
            $page = $this->cmsService->createPage($data);

            return redirect()
                ->route('admin.cms-pages.show', $page)
                ->with('success', 'CMS page created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    public function show(CmsPage $cmsPage): Response
    {
        $cmsPage->load('author');

        return Inertia::render('Admin/CmsPages/Show', [
            'page' => [
                'id' => $cmsPage->id,
                'title' => $cmsPage->title,
                'slug' => $cmsPage->slug,
                'content' => $cmsPage->content,
                'meta_description' => $cmsPage->meta_description,
                'meta_keywords' => $cmsPage->meta_keywords,
                'is_published' => $cmsPage->is_published,
                'published_at' => $cmsPage->published_at?->format('Y-m-d H:i:s'),
                'sort_order' => $cmsPage->sort_order,
                'author' => $cmsPage->author ? [
                    'id' => $cmsPage->author->id,
                    'name' => $cmsPage->author->name,
                    'email' => $cmsPage->author->email,
                ] : null,
                'featured_image_url' => $cmsPage->getFeaturedImageUrl(),
                'featured_image_thumb_url' => $cmsPage->getFeaturedImageThumbUrl(),
                'created_at' => $cmsPage->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $cmsPage->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function edit(CmsPage $cmsPage): Response
    {
        return Inertia::render('Admin/CmsPages/Edit', [
            'page' => [
                'id' => $cmsPage->id,
                'title' => $cmsPage->title,
                'slug' => $cmsPage->slug,
                'content' => $cmsPage->content,
                'meta_description' => $cmsPage->meta_description,
                'meta_keywords' => $cmsPage->meta_keywords,
                'is_published' => $cmsPage->is_published,
                'published_at' => $cmsPage->published_at?->format('Y-m-d\TH:i'),
                'sort_order' => $cmsPage->sort_order,
                'author_id' => $cmsPage->author_id,
                'featured_image_url' => $cmsPage->getFeaturedImageUrl(),
            ],
        ]);
    }

    public function update(Request $request, CmsPage $cmsPage)
    {
        try {
            $requestData = $request->all();

            // Handle unique slug validation for updates
            $requestData['slug'] = $requestData['slug'] ?? $cmsPage->slug;

            $data = CmsPageData::from($requestData);
            $updatedPage = $this->cmsService->updatePage($cmsPage, $data);

            return redirect()
                ->route('admin.cms-pages.show', $updatedPage)
                ->with('success', 'CMS page updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(CmsPage $cmsPage)
    {
        try {
            $this->cmsService->deletePage($cmsPage);

            return redirect()
                ->route('admin.cms-pages.index')
                ->with('success', 'CMS page deleted successfully.');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function togglePublish(CmsPage $cmsPage)
    {
        try {
            $updatedPage = $this->cmsService->togglePublishStatus($cmsPage);

            $message = $updatedPage->is_published
                ? 'Page published successfully.'
                : 'Page unpublished successfully.';

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function updateSortOrder(Request $request)
    {
        try {
            $orders = $request->input('orders', []);
            $this->cmsService->updateSortOrder($orders);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
