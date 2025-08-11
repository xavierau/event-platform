<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\RoleNameEnum;
use App\Modules\CMS\DataTransferObjects\CmsPageData;
use App\Modules\CMS\Models\CmsPage;
use App\Modules\CMS\Services\CmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class CmsPageController extends Controller
{
    public function __construct(
        private CmsService $cmsService
    ) {
        // Ensure only platform admins can access CMS pages
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->hasRole(RoleNameEnum::ADMIN)) {
                abort(403, 'Only platform administrators can manage CMS pages.');
            }
            return $next($request);
        });
    }

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

    public function store(CmsPageData $data): RedirectResponse
    {
        $this->cmsService->createPage($data);

        return redirect()->route('admin.cms-pages.index')->with('success', 'CMS Page created successfully.');
    }

    public function show(CmsPage $cmsPage): Response
    {
        $cmsPage->load('author');
        return Inertia::render('Admin/CmsPages/Show', [
            'page' => $cmsPage,
        ]);
    }

    public function edit(CmsPage $cmsPage): Response
    {
        return Inertia::render('Admin/CmsPages/Edit', [
            'page' => $cmsPage,
            'available_locales' => config('app.available_locales', ['en' => 'English']),
        ]);
    }

    public function update(CmsPageData $data, CmsPage $cmsPage): RedirectResponse
    {
        $this->cmsService->updatePage($cmsPage, $data);

        return redirect()->route('admin.cms-pages.index')->with('success', 'CMS Page updated successfully.');
    }

    public function destroy(CmsPage $cmsPage): RedirectResponse
    {
        $this->cmsService->deletePage($cmsPage);

        return redirect()->route('admin.cms-pages.index')->with('success', 'CMS Page deleted successfully.');
    }

    public function togglePublish(CmsPage $cmsPage): RedirectResponse
    {
        $this->cmsService->togglePublish($cmsPage);

        return back()->with('success', 'Page status updated.');
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
