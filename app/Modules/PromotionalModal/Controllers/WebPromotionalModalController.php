<?php

namespace App\Modules\PromotionalModal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PromotionalModal\DataTransferObjects\PromotionalModalData;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Services\PromotionalModalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebPromotionalModalController extends Controller
{
    public function __construct(private PromotionalModalService $promotionalModalService)
    {
        $this->authorizeResource(PromotionalModal::class, 'promotional_modal');
    }

    /**
     * Display the promotional modals admin index page.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search_title' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:modal,banner',
            'status' => 'nullable|string|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Build query with filters applied at database level
        $query = PromotionalModal::withTrashed()->byPriority();
        
        // TEMPORARY: Debug and bypass the mysterious page filtering
        $originalQuery = clone $query;
        $debugSql = $query->toSql();
        $debugBindings = $query->getBindings();
        \Log::info('Original query SQL: ' . $debugSql);
        \Log::info('Original query bindings: ' . json_encode($debugBindings));

        // Apply search filter
        if (!empty($validated['search_title'])) {
            $searchTerm = $validated['search_title'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title->en', 'like', "%{$searchTerm}%")
                    ->orWhere('title->zh-TW', 'like', "%{$searchTerm}%")
                    ->orWhere('title->zh-CN', 'like', "%{$searchTerm}%")
                    ->orWhere('content->en', 'like', "%{$searchTerm}%")
                    ->orWhere('content->zh-TW', 'like', "%{$searchTerm}%")
                    ->orWhere('content->zh-CN', 'like', "%{$searchTerm}%")
                    ->orWhere('button_text', 'like', "%{$searchTerm}%")
                    ->orWhere('button_url', 'like', "%{$searchTerm}%");
            });
        }

        // Apply type filter
        if (!empty($validated['type'])) {
            $query->forType($validated['type']);
        }

        // Apply status filter
        if (!empty($validated['status'])) {
            $isActive = $validated['status'] === 'active';
            $query->where('is_active', $isActive);
        }

        // Get paginated results - use manual pagination to bypass any automatic filtering
        $perPage = $validated['per_page'] ?? 15;
        $page = request('page', 1);
        
        \Log::info('Before paginate - SQL: ' . $query->toSql());
        \Log::info('Before paginate - Bindings: ' . json_encode($query->getBindings()));
        
        // Manual pagination to avoid any Laravel magic
        $total = $query->count();
        $items = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();
        
        // Create manual paginator
        $modals = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page'
            ]
        );

        // Transform the data for the Vue component
        $transformedModals = $modals->getCollection()->map(function ($modal) {
            return [
                'id' => $modal->id,
                'title' => $modal->title,
                'type' => $modal->type,
                'pages' => $modal->pages,
                'is_active' => $modal->is_active,
                'impressions_count' => $modal->impressions()->count(),
                'clicks_count' => $modal->impressions()->where('action', 'click')->count(),
                'conversion_rate' => $this->calculateConversionRate($modal),
                'start_at' => $modal->start_at?->toISOString(),
                'end_at' => $modal->end_at?->toISOString(),
            ];
        });

        // Create paginated response structure that matches what the Vue component expects
        $paginatedData = [
            'data' => $transformedModals->values()->all(),
            'links' => $this->buildPaginationLinks($modals),
            'from' => $modals->firstItem(),
            'to' => $modals->lastItem(),
            'total' => $modals->total(),
            'per_page' => $modals->perPage(),
        ];

        $filters = [
            'search_title' => $validated['search_title'] ?? '',
            'type' => $validated['type'] ?? '',
            'status' => $validated['status'] ?? '',
        ];

        return Inertia::render('Admin/PromotionalModals/Index', [
            'promotionalModals' => $paginatedData,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new promotional modal.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/PromotionalModals/Create');
    }

    /**
     * Show the form for editing a promotional modal.
     */
    public function edit(PromotionalModal $promotionalModal): Response
    {
        $modal = $promotionalModal->load('media');

        // Use toArray() to ensure translatable fields are properly formatted
        $modalDataArray = PromotionalModalData::from($modal->toArray())->toArray();

        // Get available locales for the frontend
        $configuredLocales = config('app.available_locales', ['en' => 'English']);
        $availableLocales = collect($configuredLocales)->map(function ($name, $code) {
            return [
                'code' => $code,
                'name' => $name,
            ];
        })->values()->toArray();

        return Inertia::render('Admin/PromotionalModals/Edit', [
            'promotionalModal' => [
                'id' => $modal->id,
                ...$modalDataArray,
                'banner_image_url' => $modal->getBannerImageUrl(),
                'background_image_url' => $modal->getBackgroundImageUrl(),
                'impressions_count' => $modal->impressions()->count(),
                'clicks_count' => $modal->impressions()->where('action', 'click')->count(),
                'conversion_rate' => $this->calculateConversionRate($modal),
            ],
            'availableLocales' => $availableLocales,
        ]);
    }

    /**
     * Store a newly created promotional modal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:modal,banner',
            'title' => 'required|array',
            'title.en' => 'required|string|max:255',
            'title.zh-TW' => 'nullable|string|max:255',
            'title.zh-CN' => 'nullable|string|max:255',
            'content' => 'required|array',
            'content.en' => 'required|string',
            'content.zh-TW' => 'nullable|string',
            'content.zh-CN' => 'nullable|string',
            'button_text' => 'nullable|string|max:100',
            'button_url' => 'nullable|url|max:500',
            'is_dismissible' => 'boolean',
            'pages' => 'nullable|array',
            'pages.*' => 'string|max:100',
            'display_frequency' => 'required|string|in:once,daily,weekly,always',
            'priority' => 'required|integer|min:0|max:100',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
            'is_active' => 'boolean',
            'uploaded_banner_image' => 'nullable|image|max:2048',
            'uploaded_background_image' => 'nullable|image|max:5120',
        ]);

        try {
            $modalData = PromotionalModalData::from($validated);
            $modal = $this->promotionalModalService->createModal($modalData);

            return redirect()->route('admin.promotional-modals.index')
                ->with('success', 'Promotional modal created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['error' => 'Failed to create promotional modal: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified promotional modal.
     */
    public function update(Request $request, PromotionalModal $promotionalModal)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:modal,banner',
            'title' => 'required|array',
            'title.en' => 'required|string|max:255',
            'title.zh-TW' => 'nullable|string|max:255',
            'title.zh-CN' => 'nullable|string|max:255',
            'content' => 'required|array',
            'content.en' => 'required|string',
            'content.zh-TW' => 'nullable|string',
            'content.zh-CN' => 'nullable|string',
            'button_text' => 'nullable|string|max:100',
            'button_url' => 'nullable|url|max:500',
            'is_dismissible' => 'boolean',
            'pages' => 'nullable|array',
            'pages.*' => 'string|max:100',
            'display_frequency' => 'required|string|in:once,daily,weekly,always',
            'priority' => 'required|integer|min:0|max:100',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
            'is_active' => 'boolean',
            'uploaded_banner_image' => 'nullable|image|max:2048',
            'uploaded_background_image' => 'nullable|image|max:5120',
        ]);

        try {
            $modalData = PromotionalModalData::from($validated);
            $modal = $this->promotionalModalService->updateModal($promotionalModal, $modalData);

            return redirect()->route('admin.promotional-modals.index')
                ->with('success', 'Promotional modal updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['error' => 'Failed to update promotional modal: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified promotional modal.
     */
    public function destroy(PromotionalModal $promotionalModal)
    {
        try {
            $this->promotionalModalService->deleteModal($promotionalModal);

            return redirect()->route('admin.promotional-modals.index')
                ->with('success', 'Promotional modal deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete promotional modal: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the analytics page for promotional modals.
     */
    public function analytics(): Response
    {
        return Inertia::render('Admin/PromotionalModals/Analytics');
    }

    /**
     * Display the analytics page for a specific promotional modal.
     */
    public function showAnalytics(PromotionalModal $promotionalModal): Response
    {
        return Inertia::render('Admin/PromotionalModals/ShowAnalytics', [
            'promotionalModal' => $promotionalModal
        ]);
    }

    /**
     * Calculate conversion rate for a modal.
     */
    private function calculateConversionRate(PromotionalModal $modal): float
    {
        $totalImpressions = $modal->impressions()->where('action', 'impression')->count();
        $clicks = $modal->impressions()->where('action', 'click')->count();

        if ($totalImpressions === 0) {
            return 0.0;
        }

        return round(($clicks / $totalImpressions) * 100, 2);
    }

    /**
     * Build pagination links array that matches the Vue component expectations.
     */
    private function buildPaginationLinks($paginator): array
    {
        $links = [];
        
        // Previous link
        $links[] = [
            'url' => $paginator->previousPageUrl(),
            'label' => '&laquo; Previous',
            'active' => false,
        ];
        
        // Page number links
        foreach (range(1, $paginator->lastPage()) as $page) {
            $links[] = [
                'url' => $paginator->url($page),
                'label' => (string) $page,
                'active' => $page === $paginator->currentPage(),
            ];
        }
        
        // Next link
        $links[] = [
            'url' => $paginator->nextPageUrl(),
            'label' => 'Next &raquo;',
            'active' => false,
        ];
        
        return $links;
    }
}